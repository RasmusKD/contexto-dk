import csv

def filter_words(input_file, output_file, max_length=5):
    with open(input_file, "r", encoding="utf-8") as infile, open(output_file, "w", encoding="utf-8", newline="") as outfile:
        reader = csv.reader(infile, delimiter="\t")
        writer = csv.writer(outfile, delimiter="\t")
        
        for row in reader:
            if len(row) >= 1:  # Tjek at der er mindst én kolonne
                word = row[0].strip()  # Første kolonne indeholder ordet
                if len(word) <= max_length:  # Filtrér på ordlængde
                    writer.writerow(row)

    print(f"Filtreringen er færdig. Resultatet er gemt i '{output_file}'.")

# Brug funktionen
if __name__ == "__main__":
    input_file = "ddo_fullforms_2023-10-11.csv"  # Inputfilen
    output_file = "filtered_words.csv"  # Outputfilen
    filter_words(input_file, output_file, max_length=5)
