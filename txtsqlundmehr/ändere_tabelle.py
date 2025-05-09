import os

# --- KONFIGURATION ---
INPUT_SQL_DUMP_FILE = os.path.join( 'assets.sql')

OUTPUT_SQL_FILE_INSERTS_ONLY = os.path.join( 'insert_isms_assets_from_dump.sql')
TARGET_ISMS_TABLE_NAME = 'assets'  # Name der Zieltabelle in der ISMS-Datenbank

# --- Hilfsfunktionen zum Parsen von Werten aus SQL INSERTs ---
def parse_sql_values_line(line):
    """
    Extrahiert Werte aus einer VALUES-Zeile wie "(1, 'Hostname', NULL, ...),".
    Dies ist eine vereinfachte Annahme über das Format.
    """
    values = []
    in_string = False
    current_value = ""
    escape_next = False

    # Entferne führende '(' und schließendes '),' oder ';'
    line = line.strip()
    if line.startswith('('):
        line = line[1:]
    if line.endswith('),'):
        line = line[:-2]
    elif line.endswith(');'):
        line = line[:-2]
    elif line.endswith(','): # Manchmal sind Dumps so formatiert
        line = line[:-1]


    for char in line:
        if escape_next:
            current_value += char
            escape_next = False
            continue
        if char == '\\':
            escape_next = True
            current_value += char # Behalte den Backslash erstmal, da er Teil des Escapings sein kann
            continue
        if char == "'" and not escape_next:
            in_string = not in_string
            current_value += char # Behalte die Anführungszeichen
            continue

        if char == ',' and not in_string:
            values.append(current_value.strip())
            current_value = ""
        else:
            current_value += char
    
    if current_value: # Letzten Wert hinzufügen
        values.append(current_value.strip())

    # Konvertiere 'NULL' zu None und entferne Anführungszeichen von Strings
    processed_values = []
    for v in values:
        if v == 'NULL':
            processed_values.append(None)
        elif v.startswith("'") and v.endswith("'"):
            # Ersetze doppelte Anführungszeichen (SQL-Escape für einfaches Anführungszeichen)
            # und andere mögliche Escapes
            processed_values.append(v[1:-1].replace("''", "'").replace("\\\\", "\\").replace("\\'", "'"))
        else:
            try:
                processed_values.append(int(v)) # Versuche int
            except ValueError:
                try:
                    processed_values.append(float(v)) # Versuche float
                except ValueError:
                    processed_values.append(v) # Behalte als String
    return processed_values


def extract_data_from_sql_dump(sql_dump_content):
    """
    Extrahiert Daten aus INSERT-Anweisungen für verschiedene Tabellen aus dem SQL-Dump.
    Gibt Dictionaries zurück, die die Daten der Tabellen enthalten.
    """
    data = {
        'devices': [], 'device_types': [], 'operating_systems': [],
        'rooms': [], 'floors': [], 'buildings': []
    }
    
    # Regex, um INSERT INTO `table_name` ... VALUES zu finden
    # und dann die Zeilen mit den Werten zu erfassen.
    # Dieses Pattern ist vereinfacht und muss ggf. an das genaue Format Ihres Dumps angepasst werden.
    insert_pattern = re.compile(r"INSERT INTO `(\w+)` \((.*?)\) VALUES\s*([\s\S]*?);", re.IGNORECASE)
    
    # Erwartete Spalten (Header) für jede Tabelle (basierend auf Ihrer devices.sql Struktur)
    # Die Reihenfolge muss der Reihenfolge der Spalten in den INSERT Statements entsprechen.
    table_headers = {
        'devices': ['device_id', 'hostname', 'ip_address', 'mac_address', 'serial_number', 'type_id', 'room_id', 'os_id', 'purchase_date', 'warranty_expiration_date', 'status', 'notes'],
        'device_types': ['type_id', 'type_name'],
        'operating_systems': ['os_id', 'os_name', 'os_version'],
        'rooms': ['room_id', 'room_name', 'floor_id'],
        'floors': ['floor_id', 'floor_name', 'building_id'],
        'buildings': ['building_id', 'building_name']
    }

    for match in insert_pattern.finditer(sql_dump_content):
        table_name = match.group(1)
        # column_list_str = match.group(2) # Spaltenliste, falls benötigt für dynamischere Zuweisung
        values_block_str = match.group(3)

        if table_name in data:
            headers = table_headers.get(table_name)
            if not headers:
                print(f"WARNUNG: Keine Header-Definition für Tabelle '{table_name}' gefunden. Überspringe.")
                continue

            # Wertezeilen trennen - Annahme: Jede Werte-Klammer ist in einer Zeile oder durch Komma getrennt
            # und endet mit ',' oder ';' für die letzte Zeile im Block.
            value_lines = re.findall(r'\((.*?)\)(?:,|$)', values_block_str, re.DOTALL)

            for line_content in value_lines:
                # Entferne führende/schließende Klammern, die von re.findall erfasst werden könnten
                line_content = line_content.strip()
                try:
                    parsed_vals = parse_sql_values_line(f"({line_content})") # Füge Klammern für Parser hinzu
                    if len(parsed_vals) == len(headers):
                        data[table_name].append(dict(zip(headers, parsed_vals)))
                    else:
                        print(f"WARNUNG: Spaltenanzahl stimmt nicht mit Header für Tabelle '{table_name}' überein. Zeile: ({line_content})")
                        print(f"  Erwartet: {len(headers)} ({headers}), Gefunden: {len(parsed_vals)} ({parsed_vals})")
                except Exception as e:
                    print(f"FEHLER beim Parsen einer Wertezeile für Tabelle '{table_name}': {line_content} - Fehler: {e}")
        # else:
            # print(f"Tabelle '{table_name}' wird nicht verarbeitet.")


    for table_name, records in data.items():
        print(f"{len(records)} Datensätze für Tabelle '{table_name}' extrahiert.")
    return data

def transform_extracted_data_and_generate_sql_inserts(extracted_data):
    """
    "Joint" und transformiert die extrahierten Daten und generiert SQL INSERT-Statements.
    """
    sql_inserts = []
    
    # Erstelle Lookup-Dictionaries für einfaches "Joinen" im Speicher
    device_types = {item['type_id']: item for item in extracted_data.get('device_types', [])}
    operating_systems = {item['os_id']: item for item in extracted_data.get('operating_systems', [])}
    rooms = {item['room_id']: item for item in extracted_data.get('rooms', [])}
    floors = {item['floor_id']: item for item in extracted_data.get('floors', [])}
    buildings = {item['building_id']: item for item in extracted_data.get('buildings', [])}

    print(f"Beginne Transformation von {len(extracted_data.get('devices', []))} Gerätedatensätzen für ISMS Assets...")

    for device in extracted_data.get('devices', []):
        # Hole verknüpfte Daten
        dev_type_info = device_types.get(device.get('type_id'))
        os_info = operating_systems.get(device.get('os_id'))
        room_info = rooms.get(device.get('room_id'))
        floor_info = floors.get(room_info.get('floor_id')) if room_info else None
        building_info = buildings.get(floor_info.get('building_id')) if floor_info else None

        # ISMS assets.name = device_type
        isms_name = dev_type_info.get('type_name', 'Unbekannter Gerätetyp') if dev_type_info else 'Unbekannter Gerätetyp'
        if not isms_name: isms_name = 'Gerät ohne Typbezeichnung'

        isms_asset_type = dev_type_info.get('type_name', 'Hardware') if dev_type_info else 'Hardware'
        if not isms_asset_type: isms_asset_type = 'Hardware'

        # ISMS assets.location
        location_parts = [
            building_info.get('building_name') if building_info else None,
            floor_info.get('floor_name') if floor_info else None,
            room_info.get('room_name') if room_info else None
        ]
        isms_location_str = ", ".join(filter(None, location_parts))
        isms_location = isms_location_str if isms_location_str else None

        # ISMS assets.description (Sammelfeld)
        description_parts = []
        if device.get('hostname'): description_parts.append(f"Hostname: {device['hostname']}")
        if device.get('ip_address'): description_parts.append(f"IP: {device['ip_address']}")
        if device.get('mac_address'): description_parts.append(f"MAC: {device['mac_address']}")
        if device.get('serial_number'): description_parts.append(f"S/N: {device['serial_number']}")
        if os_info:
            os_str = os_info.get('os_name', '')
            if os_info.get('os_version'):
                os_str += f" {os_info.get('os_version')}"
            if os_str.strip(): description_parts.append(f"OS: {os_str.strip()}")
        if device.get('status'): # device_operational_status
            description_parts.append(f"Inventar-Status: {device['status']}")
        if device.get('notes'): # device_notes
            description_parts.append(f"Inventar-Notizen: {device['notes']}")
        
        isms_description_str = "; ".join(filter(None, description_parts))
        isms_description = isms_description_str if isms_description_str else None

        isms_inventory_id_extern = str(device.get('device_id')) if device.get('device_id') is not None else None
        isms_classification = 'intern'
        isms_status_isms = 'aktiv'

        def sql_val(value):
            if value is None: return "NULL"
            return f"'{str(value).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;').replace("'", "''").replace("\\", "\\\\")}'"

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

    print(f"{len(sql_inserts)} INSERT-Statements für ISMS Assets generiert.")
    return sql_inserts

def main():
    """Hauptfunktion des Skripts."""
    print(f"Starte Verarbeitung der SQL-Dump-Datei '{INPUT_SQL_DUMP_FILE}'...")

    try:
        with open(INPUT_SQL_DUMP_FILE, 'r', encoding='utf-8') as f:
            sql_dump_content = f.read()
        print(f"SQL-Dump-Datei '{INPUT_SQL_DUMP_FILE}' erfolgreich gelesen.")
    except FileNotFoundError:
        print(f"FEHLER: Die SQL-Dump-Datei '{INPUT_SQL_DUMP_FILE}' wurde nicht gefunden.")
        return
    except Exception as e:
        print(f"FEHLER beim Lesen der SQL-Dump-Datei: {e}")
        return

    # Schritt 1: Daten aus dem SQL-Dump-Text extrahieren
    extracted_data = extract_data_from_sql_dump(sql_dump_content)

    if not extracted_data.get('devices'):
        print("Keine Gerätedaten im Dump gefunden oder extrahiert. Skript wird beendet.")
        return

    # Schritt 2: Daten transformieren und INSERT-SQL-Statements generieren
    insert_statements = transform_extracted_data_and_generate_sql_inserts(extracted_data)

    if not insert_statements:
        print("Keine INSERT-Statements zum Schreiben vorhanden. Skript wird beendet.")
        return
        
    # Schritt 3: Nur die INSERT-Statements in eine SQL-Datei schreiben
    try:
        with open(OUTPUT_SQL_FILE_INSERTS_ONLY, 'w', encoding='utf-8') as f:
            f.write(f"-- SQL INSERT Statements für die Tabelle '{TARGET_ISMS_TABLE_NAME}'\n")
            f.write(f"-- Generiert durch Parsen der Datei '{INPUT_SQL_DUMP_FILE}'.\n")
            f.write(f"-- {len(insert_statements)} Geräte transformiert.\n")
            f.write("-- Bitte stellen Sie sicher, dass die Zieltabelle bereits existiert und die Spaltennamen passen.\n\n")
            
            for stmt in insert_statements:
                f.write(stmt + "\n")
            
            f.write(f"\n-- {len(insert_statements)} INSERT-Statements für Assets abgeschlossen --\n")

        print(f"SQL-Datei '{OUTPUT_SQL_FILE_INSERTS_ONLY}' mit INSERT-Statements erfolgreich geschrieben.")
    except IOError as e:
        print(f"FEHLER beim Schreiben der SQL-Datei '{OUTPUT_SQL_FILE_INSERTS_ONLY}': {e}")

if __name__ == '__main__':
    main()