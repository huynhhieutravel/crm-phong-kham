import json

with open('translate_dict.json', 'r') as f:
    data = json.load(f)

# Auto-translate dictionary based on German/Vietnamese keys
def translate_to_english(de, vi):
    de = de.strip()
    vi = vi.strip()
    
    # Common standard phrases
    mapping = {
        "Name": "Full Name",
        "Geburtsjahr": "Birth Year",
        "Geburtsdatum": "Date of Birth",
        "< 1J": "< 1Y",
        "Beziehung": "Relationship",
        "-- Patient wählen --": "-- Select Patient --",
        "Geschlecht": "Gender",
        "Besuch": "Visit",
        "Letzte Behandlung": "Last Treatment",
        "Beinbelastung (kg)": "Leg Load (kg)",
        "li...": "Left...",
        "re...": "Right...",
        "Patientenbilder (max. 4 Bilder)": "Patient Images (max 4 images)",
        "Hochladen": "Upload",
        "Klicken zum Vergrößern": "Click to Enlarge",
        "Dieses Bild löschen": "Delete this Image",
        "Speichern": "Save",
        "Neu erstellen": "Create New",
        "Notizfeld": "Note Field",
        "Notizen:": "Notes:",
        "Hier Anmerkungen oder zusätzliche Informationen eingeben...": "Enter notes or additional information here...",
        "Nach dem Speichern werden diese Notizen zusammengefasst und gemeinsam mit den obigen Notizen angezeigt. Notizen aus der Anamnese werden auf den späteren Follow-Up-Bögen erneut angezeigt.": "After saving, these notes will be combined and displayed with the above notes. Notes from the Anamnesis will be displayed again on later Follow-Up forms.",
        "Nach dem Speichern wird diese Notiz mit den obigen Notizen zusammengefasst. Notizen in der Anamnese werden in zukünftigen Follow-Ups wieder angezeigt.": "After saving, this note will be merged with the above notes. Notes in the Anamnesis will be displayed in future Follow-ups.",
        
        # Medical - Head & Spine
        "Kopf": "Head",
        "HWS": "Cervical Spine",
        "BWS": "Thoracic Spine",
        "LWS": "Lumbar Spine",
        "Schulter": "Shoulder",
        "Obere Extr.": "Upper Extr.",
        "Untere Extr.": "Lower Extr.",
        "Fuß & Sprunggelenk": "Foot & Ankle",
        
        # Table Headers
        "Symptom / Bereich": "Symptom / Area",
        "Eigenschaften / Ort": "Characteristics / Location",
        "Häufigkeit / Status": "Frequency / Status",
        "Symptome & Details": "Symptoms & Details",
        "Status / Ausstrahlung": "Status / Radiation",
        "Bereich": "Area",
        "Symptome / Ort": "Symptoms / Location",
        "Einschränkungen": "Limitations",
        "Gelenk / Bereich": "Joint / Area",
        "Symptome / Lokalisation": "Symptoms / Localization",
        "Status": "Status",
        "Symptome & Befunde": "Symptoms & Findings",
        "Klinisches Bild": "Clinical Picture",
        "Lokalisation & Anatomie": "Localization & Anatomy",
        "Fußdeformitäten": "Foot Deformities",
        
        # Genders
        "Männlich": "Male",
        "Weiblich": "Female",
        "Unbekannt": "Unknown",
        "Andere": "Other",
        "Selbst": "Self",
        "Erster Besuch": "First Visit",
        
        # Symptoms
        "Schwindel": "Dizziness",
        "Tinnitus": "Tinnitus",
        "Ohrgeräusche (rauschen)": "Tinnitus (rushing)",
        "pfeifen": "Tinnitus (whistling)",
        "Kiefergelenk (TMJ)": "TMJ (Temporomandibular Joint)",
        "knacken": "Cracking",
        "schmerzen": "Pain",
        "eingeschränkte Bewegung": "Restricted Movement",
        
        "obere": "Upper",
        "mittlere": "Middle",
        "untere": "Lower",
        
        "akut": "Acute",
        "chronisch": "Chronic",
        "wiederkehrend": "Recurrent",
        
        "bei Bewegung": "On movement",
        "in Ruhe": "At rest",
        "stechend": "Sharp",
        "dumpf": "Dull",
        "taub": "Numbness",
        "Schwäche": "Weakness",
        "Lähmung": "Paralysis",
        "Ausstrahlung:": "Radiation:",
        "Bewegungseinschränkung nach:": "Movement restriction to:",
        "bestimmte Richtung": "Specific direction",
        
        "dorsal von HWS": "Dorsal of Cervical Spine",
        "frontal": "Frontal",
        "lateral": "Lateral",
        "einseitig": "Unilateral",
        
        "täglich": "Daily",
        "2-3 mal/Woche": "2-3 times/week",
        "1 mal/Woche": "1 time/week",
        "1 mal/Monat": "1 time/month",
        "gelegentlich": "Occasionally",
        
        "Seit wann:": "Since when:",
        "Tage": "Days",
        "Wochen": "Weeks",
        "Monate": "Months",
        "Jahre": "Years",
        "durchgehend": "Continuous",
        "mal mehr, mal weniger": "Fluctuating",
        "zeitweise schmerzfrei": "Intermittently pain-free",
        
        "bis Oberarm": "To upper arm",
        "bis Hand": "To hand",
        "bis Gesäß": "To buttocks",
        "bis Knie": "To knee",
        "bis Fuß": "To foot",
        "Vorderseite": "Anterior",
        "Rückseite": "Posterior",
        "Außenseite": "Lateral side",
        "Innenseite": "Medial side",
        
        "Trägt Zahnspange / Zahnimplantate": "Wears Braces / Dental Implants",
        "Zahnspange": "Braces",
        "Zahnspange in der Vergangenheit": "Braces in the past",
        "Zahnbrücke": "Dental Bridge",
        "Zahnkrone": "Dental Crown",
        "Zahnimplantat": "Dental Implant",
        "wurzelbehandelter Zahn": "Root canal treated tooth",
        
        "TEIL 1: BASISDATEN & LEBENSSTIL": "PART 1: BASIC DATA & LIFESTYLE",
        "TEIL 2: SYMPTOME": "PART 2: SYMPTOMS",
        "TEIL 3: AKTUELLE PATHOLOGIE": "PART 3: CURRENT PATHOLOGY",
        "TEIL 2 – AKTUELLE PATHOLOGIE": "PART 2 - CURRENT PATHOLOGY",
        
        "ICN:": "Intercostal Neuralgia (ICN):",
        "ventral": "Ventral",
        "dorsal": "Dorsal",
        
        "Ellenbogen": "Elbow",
        "Tennisarm (TA)": "Tennis Elbow (TA)",
        "Golferarm (GA)": "Golfer's Elbow (GA)",
        
        "Handgelenk": "Wrist",
        "Ossa metacarpalia": "Metacarpals (MTC)",
        "Daumen": "Thumb",
        "Digiti": "Fingers",
        "Carpaltunnelsyndrom (CTS)": "Carpal Tunnel Syndrome (CTS)",
        
        "Sattelgelenk - SG": "Saddle Joint - SG",
        "Grundgelenk - GG": "Metacarpophalangeal Joint - MCP",
        
        "Bein": "Leg",
        "Knie": "Knee",
        "Längendifferenz bekannt:": "Leg length discrepancy known:",
        "kürzeres Bein:": "Shorter leg:",
        
        "Meniskus": "Meniscus",
        "Innenmeniskus": "Medial Meniscus",
        "Außenmeniskus": "Lateral Meniscus",
        "sụn chêm trong - Innenmeniskus": "Medial Meniscus",
        "sụn chêm ngoài - Außenmeniskus": "Lateral Meniscus",
        "Fibula": "Fibula",
        "ganzer Meniskus": "Whole Meniscus",
        "hinterer Meniskus": "Posterior Meniscus",
        
        "schmerzbedingt": "Due to pain",
        "blockadebedingt": "Due to blockage",
        "Ödem": "Edema",
        "Schwierigkeiten bei:": "Difficulty with:",
        "Treppe auf": "Stairs up",
        "Treppe ab": "Stairs down",
        "Gehen": "Walking",
        "Stehen": "Standing",
        "Sitzen": "Sitting",
        
        "Fersensporn": "Heel Spur",
        "Hallux valgus": "Hallux Valgus",
        "Morton Neurom": "Morton's Neuroma",
        
        "plantar": "Plantar",
        "proximal": "Proximal",
        "distal": "Distal",
        "medial": "Medial",
        "Zehe:": "Toe:",
        
        "Plattfuß": "Flat Foot",
        "Hohlfuß": "Cavus Foot",
        "Sichelfuß": "Skew Foot",
        "Senkfuß": "Fallen Arches",
        "Spreizfuß": "Splay Foot",
        "Knickfuß": "Valgus Foot",
        
        "Sprunggelenk": "Ankle",
        "OSG (Oberes Sprunggelenk)": "Upper Ankle Joint (OSG)",
        "USG (Unteres Sprunggelenk):": "Lower Ankle Joint (USG):",
        "OSG": "Upper Ankle Joint (OSG)",
        "USG": "Lower Ankle Joint (USG)",
        "sup": "Supination (sup)",
        "pron": "Pronation (pron)",
        
        "Bild auswählen, auf <strong>Hochladen</strong> klicken, dann am Ende der Seite auf <strong>Speichern</strong> klicken.": "Select image, click <strong>Upload</strong>, then click <strong>Save</strong> at the bottom of the page.",
        
        # Follow-up V2 Specifics
        "Vergleichsskizzen: Vorheriges Bild ↔ Aktuelles Bild": "Comparison sketches: Previous Image ↔ Current Image",
        "Datum der ersten Untersuchung:": "Date of first examination:",
        "Datum des Follow-Ups:": "Date of Follow-Up:",
        "Follow-up V2 (Neu)": "Follow-up V2 (New)",
        "Dieses Mal": "This Time",
        "Letztes Mal": "Last Time",
        "Vorheriges Bild": "Previous Image",
        "Aktuelles Bild": "Current Image",
        "Nicht vorhanden": "Not available",
        
        "Verbessert": "Improved",
        "Unverändert": "Unchanged",
        "Verschlechtert": "Worsened",
        "Deutlich verbessert": "Significantly improved",
        "Leicht verbessert": "Slightly improved",
        "Leicht verschlechtert": "Slightly worsened",
        "Deutlich verschlechtert": "Significantly worsened",
        
        "Behandlungsliste – Obere Extremitäten": "Treatment List - Upper Extremities",
        "Behandlungsliste – Untere Extremitäten": "Treatment List - Lower Extremities",
        "Struktur / Gelenk": "Structure / Joint",
        "ISG & Becken": "Sacroiliac Joint (SIJ) & Pelvis",
        "Sacrum": "Sacrum",
        "Rippen": "Ribs",
        "Becken": "Pelvis",
        "Symphyse:": "Symphysis:",
        "Os coccygis:": "Coccyx (Os coccygis):",
        "cranial": "Cranial",
        "caudal": "Caudal",
        "Rippe 1": "Rib 1",
        "Bizeps": "Biceps",
        "Rotatorenmanschette": "Rotator Cuff",
        "ACG": "AC Joint (ACG)",
        "SCG": "SC Joint (SCG)",
        "Proc. coracoideus": "Coracoid Process",
        "Supraspinatus": "Supraspinatus",
        "Handwurzelknochen / CTS": "Carpal Bones / CTS",
        "CTS": "CTS",
        "Tennisarm (TA):": "Tennis Elbow (TA):",
        "Golferarm (GA):": "Golfer's Elbow (GA):",
        "med": "Medial",
        "lat": "Lateral",
        "Metacarpalia:": "Metacarpals:",
        "Daumen:": "Thumb:",
        "Sattelgelenk": "Saddle Joint",
        "Grundgelenk": "Metacarpophalangeal Joint",
        "USG:": "Lower Ankle Joint (USG):",
        "OSG:": "Upper Ankle Joint (OSG):",
        "post": "Posterior",
        "ant": "Anterior",
        "Os cuboideum": "Cuboid Bone",
        "Os naviculare": "Navicular Bone",
        "Kniegelenk": "Knee Joint",
        "Patellamobilität": "Patellar Mobility",
        "Hüftgelenk": "Hip Joint",
        "Iliopsoas": "Iliopsoas",
        "Ossa cuneiformia:": "Cuneiform Bones:",
        "mediale": "Medial",
        "intermedium": "Intermediate",
        "laterale": "Lateral",
        "Metatarsalia (MTT):": "Metatarsals (MTT):",
        
        "Occiput": "Occiput",
        "Atlas (C1)": "Atlas (C1)",
        "Axis (C2)": "Axis (C2)",
        
        "li": "L",
        "re": "R",
        
        "Datei auswählen": "Choose file",
        "Keine Datei ausgewählt": "No file chosen",
        "Dateien ausgewählt": "files chosen",
        "Notizen für diese Behandlung eingeben...": "Enter notes for this treatment...",
        "Notizen...": "Notes...",
        "(Rotes X auf dem Bild oben markieren)": "(Mark red X on the image above)",
        
        "T": "L",
        "P": "R",
    }
    
    if de in mapping:
        return mapping[de]
        
    # Heuristic matching if exact match not found
    for key, val in mapping.items():
        if de == key: return val
        
    return ""

for item in data:
    item['en'] = translate_to_english(item['de'], item['vi'])

with open('translate_dict_en.json', 'w') as f:
    json.dump(data, f, indent=4, ensure_ascii=False)

empty_count = sum(1 for item in data if not item['en'])
print(f"Generated English translations. Missing translations: {empty_count}")
