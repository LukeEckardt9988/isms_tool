import re
import os

# --- KONFIGURATION ---
# Annahme: 'assets.sql' (Ihr Dump) liegt im selben Verzeichnis wie dieses Skript
script_dir = os.path.dirname(os.path.abspath(__file__))
INPUT_SQL_DUMP_FILE = os.path.join(script_dir, 'assets.sql') # Ihre SQL-Dump-Datei

OUTPUT_SQL_FILE_INSERTS_ONLY = os.path.join(script_dir, 'insert_isms_assets_filtered.sql')
TARGET_ISMS_TABLE_NAME = 'assets' # Name Ihrer existierenden Asset-Tabelle in der epsa_isms DB

def parse_sql_value(value_str):
    """
    Konvertiert einen einzelnen SQL-Wert-String (z.B. "'Text'", "123", "NULL")
    in einen Python-Typ (string, int, None).
    Entfernt äußere Anführungszeichen von Strings und behandelt SQL-NULL und Escaping.
    """
    value_str = value_str.strip()
    if value_str.upper() == 'NULL':
        return None
    if value_str.startswith("'") and value_str.endswith("'"):
        # Ersetze SQL-escaped einfache Anführungszeichen ('') und Backslashes (\\)
        return value_str[1:-1].replace("''", "'").replace("\\\\", "\\").replace("\\'", "'").replace('\\"', '"')
    try:
        # Versuche, als Ganzzahl zu parsen
        return int(value_str)
    except ValueError:
        try:
            # Versuche, als Fließkommazahl zu parsen
            return float(value_str)
        except ValueError:
            # Wenn alles andere fehlschlägt, als String belassen
            return value_str

def parse_sql_values_line(line_content):
    """
    Extrahiert Werte aus dem Inhalt einer VALUES-Klammer, z.B. "1, 'Hostname', NULL".
    Diese Funktion muss robust genug für SQL-String-Literale mit Kommas sein.
    """
    values = []
    current_val = ''
    in_string = False
    escape_next = False
    idx = 0
    while idx < len(line_content):
        char = line_content[idx]
        if escape_next:
            current_val += char
            escape_next = False
        elif char == '\\':
            escape_next = True
            current_val += char # Behalte den Backslash
        elif char == "'":
            in_string = not in_string
            current_val += char
        elif char == ',' and not in_string:
            values.append(parse_sql_value(current_val))
            current_val = ''
        else:
            current_val += char
        idx += 1
    
    if current_val: # Letzten Wert hinzufügen
        values.append(parse_sql_value(current_val))
    return values

def extract_data_from_sql_dump(sql_dump_content):
    """
    Extrahiert Daten aus INSERT-Anweisungen für verschiedene Tabellen aus dem SQL-Dump-Text.
    """
    data = {
        'devices': [], 
        # 'device_types': [], # device_type ist direkt in devices
        # 'operating_systems': [], # os_name/version nicht in separater Tabelle in assets.sql
        'rooms': [], 'floors': [], 'buildings': []
    }
    
    # Angepasste Regex für `assets.sql`, die die Spaltennamen explizit enthält
    insert_pattern = re.compile(
        r"INSERT IGNORE INTO `(\w+)` \((.*?)\) VALUES\s*([\s\S]*?);", 
        re.IGNORECASE
    )
    
    # Spaltenüberschriften basierend auf Ihrer `assets.sql` für die relevanten Tabellen
    # Die Reihenfolge muss exakt der Reihenfolge der Spalten in den INSERT-Anweisungen entsprechen.
    # Diese sind direkt aus Ihrer `assets.sql` (die Sie hochgeladen haben) abgeleitet.
    # Die Tabelle `devices` in Ihrer `assets.sql` hat die Spalten `device_id`, `workplace_id`, `device_type`, usw.
    # Die Tabellen `buildings`, `floors`, `rooms` haben ebenfalls definierte Spalten.
    
    # Wir lesen die Spalten direkt aus der `INSERT`-Zeile.
    
    print("Beginne Extraktion aus SQL-Dump...")
    for match in insert_pattern.finditer(sql_dump_content):
        table_name = match.group(1)
        column_list_str = match.group(2).strip()
        values_block_str = match.group(3)

        if table_name in data:
            headers = [col.strip().replace('`', '') for col in column_list_str.split(',')]
            
            # Wertezeilen trennen
            value_lines_raw = re.findall(r'\((.*?)\)(?:,\s*|$)', values_block_str, re.DOTALL)

            for line_content in value_lines_raw:
                line_content = line_content.strip()
                if not line_content: continue # Überspringe leere Zeilen

                try:
                    parsed_vals = parse_sql_values_line(line_content)
                    if len(parsed_vals) == len(headers):
                        data[table_name].append(dict(zip(headers, parsed_vals)))
                    else:
                        print(f"WARNUNG: Spaltenanzahl ({len(parsed_vals)}) stimmt nicht mit Header-Anzahl ({len(headers)}) für Tabelle '{table_name}' überein.")
                        print(f"  Header: {headers}")
                        print(f"  Werte:  {parsed_vals}")
                        print(f"  Zeile:  ({line_content})")
                except Exception as e:
                    print(f"FEHLER beim Parsen einer Wertezeile für Tabelle '{table_name}': ({line_content}) - Fehler: {e}")
        # else:
            # print(f"Tabelle '{table_name}' wird nicht von diesem Skript verarbeitet.")

    for table_name_key, records in data.items():
        print(f"{len(records)} Datensätze für Tabelle '{table_name_key}' aus Dump extrahiert.")
    return data

def transform_extracted_data_and_generate_sql_inserts(extracted_data):
    """
    "Joint" und transformiert die extrahierten Daten und generiert SQL INSERT-Statements.
    Filtert "Monitor" Einträge heraus.
    """
    sql_inserts = []
    
    # Erstelle Lookup-Dictionaries für einfaches "Joinen" im Speicher
    # (device_type ist direkt in der devices Tabelle Ihrer assets.sql)
    # (operating_system ist in Ihrer assets.sql nicht als separate Tabelle, sondern als Spalte in devices, wenn vorhanden)
    rooms_lookup = {item['room_id']: item for item in extracted_data.get('rooms', []) if 'room_id' in item}
    floors_lookup = {item['floor_id']: item for item in extracted_data.get('floors', []) if 'floor_id' in item}
    buildings_lookup = {item['building_id']: item for item in extracted_data.get('buildings', []) if 'building_id' in item}

    processed_devices_count = 0
    skipped_monitors_count = 0

    for device_row in extracted_data.get('devices', []):
        # Filter: Überspringe Geräte vom Typ "Monitor"
        device_type_from_dump = device_row.get('device_type', '').strip()
        if device_type_from_dump.lower() == 'monitor':
            skipped_monitors_count += 1
            continue
        
        processed_devices_count +=1

        # ISMS assets.name = device.device_type
        isms_name = device_type_from_dump if device_type_from_dump else 'Unbekannter Gerätetyp'
        if not isms_name: isms_name = 'Gerät ohne Typbezeichnung'

        # ISMS assets.asset_type (kann auch device_type sein oder generischer)
        isms_asset_type = device_type_from_dump if device_type_from_dump else 'Hardware'
        if not isms_asset_type: isms_asset_type = 'Hardware'


        # ISMS assets.location
        building_name_val = None
        if device_row.get('building_id') is not None:
            building_info = buildings_lookup.get(device_row.get('building_id'))
            if building_info:
                building_name_val = building_info.get('building_name')
        
        floor_name_val = None
        if device_row.get('floor_id') is not None:
            floor_info = floors_lookup.get(device_row.get('floor_id'))
            if floor_info:
                floor_name_val = floor_info.get('floor_name')

        room_name_val = None
        if device_row.get('room_id') is not None:
            room_info = rooms_lookup.get(device_row.get('room_id'))
            if room_info:
                room_name_val = room_info.get('room_name')

        location_parts = [building_name_val, floor_name_val, room_name_val]
        isms_location_str = " ".join(filter(None, location_parts)) # Mit Leerzeichen getrennt
        isms_location = isms_location_str.strip() if isms_location_str.strip() else None


        # ISMS assets.description (Sammelfeld)
        description_parts = []
        # Die Spaltennamen hier müssen exakt denen in Ihrer `devices` Tabelle (aus assets.sql) entsprechen
        if device_row.get('hostname'): description_parts.append(f"Hostname: {device_row['hostname']}")
        if device_row.get('ip_address'): description_parts.append(f"IP: {device_row['ip_address']}")
        if device_row.get('mac_address'): description_parts.append(f"MAC: {device_row['mac_address']}")
        if device_row.get('serial_number'): description_parts.append(f"S/N: {device_row['serial_number']}")
        if device_row.get('inventory_number'): description_parts.append(f"Inventar-Nr: {device_row['inventory_number']}")
        if device_row.get('manufacturer'): description_parts.append(f"Hersteller: {device_row['manufacturer']}")
        if device_row.get('model'): description_parts.append(f"Modell: {device_row['model']}")
        # In Ihrer `assets.sql` gibt es keine Spalte os_name oder os_version in der `devices` Tabelle.
        # Sie müssten diese Information aus `device_notes` parsen oder es ist nicht vorhanden.
        if device_row.get('status'): # Operativer Status
            description_parts.append(f"Inventar-Status: {device_row['status']}")
        if device_row.get('notes'): # Notizen aus Inventar
            description_parts.append(f"Inventar-Notizen: {device_row['notes']}")
        
        isms_description_str = "; ".join(filter(None, description_parts))
        isms_description = isms_description_str if isms_description_str else None

        isms_inventory_id_extern = str(device_row.get('device_id')) if device_row.get('device_id') is not None else None
        isms_classification = 'intern'
        isms_status_isms = 'aktiv'

        def sql_val(value):
            if value is None: return "NULL"
            return f"'{str(value).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;').replace("'", "''").replace("\\", "\\\\")}'"

        # WICHTIG: Passen Sie die Spaltenliste an Ihre existierende 'assets'-Tabelle in 'epsa_isms' an!
        insert_sql = (
            f"INSERT INTO `{TARGET_ISMS_TABLE_NAME}` ("
            f"`name`, `asset_type`, `location`, `description`, `inventory_id_extern`, "
            f"`classification`, `status_isms`"
            f") VALUES ("
            f"{sql_val(isms_name)}, {sql_val(isms_asset_type)}, {sql_val(isms_location)}, "
            f"{sql_val(isms_description)}, {sql_val(isms_inventory_id_extern)}, "
            f"{sql_val(isms_classification)}, {sql_val(isms_status_isms)}"
            f");"
        )
        sql_inserts.append(insert_sql)
    
    print(f"{len(sql_inserts)} INSERT-Statements für ISMS Assets (ohne Monitore) generiert.")
    print(f"{skipped_monitors_count} Monitore wurden übersprungen.")
    return sql_inserts

def main():
    """Hauptfunktion des Skripts."""
    print(f"Starte Verarbeitung der SQL-Dump-Datei '{INPUT_SQL_DUMP_FILE}'...")

    try:
        with open(INPUT_SQL_DUMP_FILE, 'r', encoding='utf-8') as f:
            sql_dump_content = f.read()
        print(f"SQL-Dump-Datei '{INPUT_SQL_DUMP_FILE}' erfolgreich gelesen (Größe: {len(sql_dump_content)} Bytes).")
    except FileNotFoundError:
        print(f"FEHLER: Die SQL-Dump-Datei '{INPUT_SQL_DUMP_FILE}' wurde nicht gefunden.")
        print(f"Erwarteter Pfad: {os.path.abspath(INPUT_SQL_DUMP_FILE)}")
        return
    except Exception as e:
        print(f"FEHLER beim Lesen der SQL-Dump-Datei: {e}")
        return

    extracted_data = extract_data_from_sql_dump(sql_dump_content)

    if not extracted_data.get('devices'):
        print("Keine Gerätedaten im Dump gefunden oder extrahiert. Skript wird beendet.")
        return

    insert_statements = transform_extracted_data_and_generate_sql_inserts(extracted_data)

    if not insert_statements:
        print("Keine INSERT-Statements zum Schreiben vorhanden. Skript wird beendet.")
        return
        
    try:
        with open(OUTPUT_SQL_FILE_INSERTS_ONLY, 'w', encoding='utf-8') as f:
            f.write(f"-- SQL INSERT Statements für die Tabelle '{TARGET_ISMS_TABLE_NAME}'\n")
            f.write(f"-- Generiert durch Parsen der Datei '{INPUT_SQL_DUMP_FILE}'.\n")
            f.write(f"-- {len(insert_statements)} Geräte (exkl. Monitore) transformiert.\n")
            f.write("-- Bitte stellen Sie sicher, dass die Zieltabelle bereits existiert und die Spaltennamen passen.\n\n")
            
            for stmt in insert_statements:
                f.write(stmt + "\n")
            
            f.write(f"\n-- {len(insert_statements)} INSERT-Statements für Assets abgeschlossen --\n")

        print(f"SQL-Datei '{OUTPUT_SQL_FILE_INSERTS_ONLY}' mit INSERT-Statements erfolgreich geschrieben.")
    except IOError as e:
        print(f"FEHLER beim Schreiben der SQL-Datei '{OUTPUT_SQL_FILE_INSERTS_ONLY}': {e}")

if __name__ == '__main__':
    main()