import re
import csv # Für CSV-Ausgabe

# --- KONFIGURATION ---
TXT_FILE_PATH = 'bsi.txt'  # Pfad zu Ihrer Textdatei
OUTPUT_FORMAT = 'sql'  # 'sql' oder 'csv'
SQL_OUTPUT_FILE = 'bsi_controls_import.sql'
CSV_OUTPUT_FILE = 'bsi_controls_import.csv'
TABLE_NAME = 'controls' # Name Ihrer Zieltabelle in der Datenbank

# --- REGULÄRE AUSDRÜCKE (Müssen ggf. weiterhin angepasst werden!) ---
# Erkennt eine typische Anforderungs-ID und den Titel.
ANFORDERUNG_PATTERN = re.compile(
    r'^\s*([A-Z]{2,4}\.\d{1,2}(\.\d{1,2})?\.A\d{1,3})\s+(.+?)\s*$',  # ID und Titel in einer Zeile
    re.MULTILINE
)

# --- HAUPTFUNKTION ZUM PARSEN UND EXPORTIEREN ---
def parse_and_export_from_txt():
    controls_data = [] # Liste zum Sammeln der extrahierten Control-Daten
    controls_found_count = 0

    try:
        with open(TXT_FILE_PATH, 'r', encoding='utf-8') as f:
            full_text_content = f.read()
        print(f"Textdatei '{TXT_FILE_PATH}' erfolgreich gelesen.")

        print("Suche nach Anforderungen im Text...")

        matches = list(ANFORDERUNG_PATTERN.finditer(full_text_content))

        for i, current_match in enumerate(matches):
            control_id_iso = current_match.group(1).strip()
            name = current_match.group(3).strip()

            start_of_description = current_match.end()
            if i + 1 < len(matches):
                end_of_description = matches[i+1].start()
            else:
                end_of_description = len(full_text_content)

            description_raw = full_text_content[start_of_description:end_of_description].strip()
            description_lines = [line.strip() for line in description_raw.splitlines() if line.strip()]
            description = " ".join(description_lines)
            description = re.sub(r'\s{2,}', ' ', description)

            if i + 1 < len(matches):
                next_control_id_pattern = re.escape(matches[i+1].group(1).strip())
                description = re.sub(r'^' + next_control_id_pattern + r'\s*', '', description).strip()

            controls_found_count += 1
            print(f"  Gefunden: ID='{control_id_iso}', Titel='{name}'")
            # print(f"    Beschreibung (gekürzt): '{description[:100]}...'")

            # Standardwerte für die Felder, die nicht aus dem Text kommen
            # 'type' ist hier nicht enthalten, da ENUM und oft spezifisch
            control_entry = {
                'control_id_iso': control_id_iso,
                'name': name,
                'description': description if description else None,
                # 'type': None, # oder ein Standardwert wie 'leitend', 'präventiv' etc.
                'implementation_status': 'geplant',
                'effectiveness': 'nicht bewertet',
                'justification_applicability': None,
                'owner_id': None, # Wird später im ISMS-Tool zugewiesen
                'last_review_date': None
            }
            controls_data.append(control_entry)

    except FileNotFoundError:
        print(f"Fehler: Die Textdatei '{TXT_FILE_PATH}' wurde nicht gefunden.")
        return
    except Exception as e:
        print(f"Ein Fehler ist beim Verarbeiten der Textdatei aufgetreten: {e}")
        return

    if not controls_data:
        print("Keine Controls zum Exportieren gefunden.")
        return

    # --- DATEN IN GEWÜNSCHTES FORMAT SCHREIBEN ---
    if OUTPUT_FORMAT.lower() == 'sql':
        try:
            with open(SQL_OUTPUT_FILE, 'w', encoding='utf-8') as f_sql:
                f_sql.write(f"-- SQL Export für BSI Controls ({len(controls_data)} Einträge)\n")
                f_sql.write(f"-- Zieltabelle: {TABLE_NAME}\n\n")
                for control in controls_data:
                    # Bereite Werte für SQL vor (escapen, NULLs korrekt behandeln)
                    desc_val = f"'{control['description'].replace("'", "''")}'" if control['description'] else "NULL"
                    just_val = f"'{control['justification_applicability'].replace("'", "''")}'" if control['justification_applicability'] else "NULL"
                    owner_val = str(control['owner_id']) if control['owner_id'] else "NULL"
                    review_val = f"'{control['last_review_date']}'" if control['last_review_date'] else "NULL"
                    # type_val = f"'{control['type']}'" if control.get('type') else "NULL" # Falls 'type' hinzugefügt wird

                    # Beachten Sie die Spaltenreihenfolge und -namen Ihrer `controls`-Tabelle!
                    # Hier sind die Spalten, die wir in der Datenbank haben (ohne id, created_at, updated_at)
                    f_sql.write(
                        f"INSERT INTO `{TABLE_NAME}` (`control_id_iso`, `name`, `description`, `implementation_status`, `effectiveness`, `justification_applicability`, `owner_id`, `last_review_date`) VALUES ("
                        f"'{control['control_id_iso'].replace("'", "''")}', "
                        f"'{control['name'].replace("'", "''")}', "
                        f"{desc_val}, "
                        # f"{type_val}, " # Falls 'type' hinzugefügt wird
                        f"'{control['implementation_status']}', "
                        f"'{control['effectiveness']}', "
                        f"{just_val}, "
                        f"{owner_val}, "
                        f"{review_val}"
                        f");\n"
                    )
                print(f"Daten erfolgreich in '{SQL_OUTPUT_FILE}' geschrieben.")
        except IOError:
            print(f"Fehler beim Schreiben der SQL-Datei '{SQL_OUTPUT_FILE}'.")

    elif OUTPUT_FORMAT.lower() == 'csv':
        # Spalten für die CSV-Datei definieren (entsprechend Ihrer `controls`-Tabelle)
        # Wichtig: Die Reihenfolge hier bestimmt die Reihenfolge in der CSV-Datei.
        fieldnames = [
            'control_id_iso', 'name', 'description',
            'implementation_status', 'effectiveness',
            'justification_applicability', 'owner_id', 'last_review_date'
            # 'type' # Falls hinzugefügt
        ]
        try:
            with open(CSV_OUTPUT_FILE, 'w', newline='', encoding='utf-8') as f_csv:
                writer = csv.DictWriter(f_csv, fieldnames=fieldnames, quoting=csv.QUOTE_MINIMAL)
                writer.writeheader()
                for control in controls_data:
                    # Stelle sicher, dass nur die definierten Felder geschrieben werden
                    row_to_write = {key: control.get(key) for key in fieldnames}
                    writer.writerow(row_to_write)
                print(f"Daten erfolgreich in '{CSV_OUTPUT_FILE}' geschrieben.")
        except IOError:
            print(f"Fehler beim Schreiben der CSV-Datei '{CSV_OUTPUT_FILE}'.")
    else:
        print(f"Unbekanntes Ausgabeformat: {OUTPUT_FORMAT}. Bitte 'sql' oder 'csv' wählen.")


    print(f"\n--- Zusammenfassung ---")
    print(f"Anforderungen in Textdatei gefunden (basierend auf Pattern): {controls_found_count}")
    print(f"Anforderungen für Export vorbereitet: {len(controls_data)}")

if __name__ == '__main__':
    parse_and_export_from_txt()