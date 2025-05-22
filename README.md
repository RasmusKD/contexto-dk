# Contexto DK

En dansk version af det populære ordgættespil Contexto. Gæt det hemmelige ord ved hjælp af kontekstuelle hints baseret på ordenes semantiske lighed.

## 🎮 Hvordan spiller man?

1. **Gæt et ord** - Skriv et dansk ord i inputfeltet
2. **Se din placering** - Få at vide hvor tæt dit gæt er på det hemmelige ord
3. **Brug hints** - Jo lavere nummer, jo tættere er du på svaret
4. **Find ordet** - Fortsæt indtil du finder det rigtige ord!

## ✨ Features

- 🎯 Dagligt nyt ord
- 📊 Progressbar der viser hvor tæt du er
- 💡 Hint-system
- 📱 Responsivt design
- 🌙 Mørk/lys tema
- 🎨 Smooth animationer
- 📈 **TODO** Statistikker og streak tracking

## 🛠️ Teknologi

- **Backend**: PHP 8+ med custom MVC framework
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Styling**: Tailwind CSS
- **Database**: SQLite
- **Dependencies**: Composer

## 📁 Projektstruktur

contexto-dk/
├── app/
│   ├── Config/
│   │   ├── words.php          # Daglige ord
│   │   └── database.php       # Database konfiguration
│   ├── Controllers/
│   │   └── GameController.php # Spil logik
│   ├── Core/
│   │   ├── DailyWord.php      # Håndtering af daglige ord
│   │   ├── Lemmatizer.php     # Dansk ordlemmatisering og ordvarianter
│   │   └── Database.php       # Database forbindelse
│   ├── Models/
│   │   └── GameModel.php      # Data access layer - MongoDB queries
│   └── Views/
│       └── game.php           # Hovedspil interface
├── public/
│   ├── index.php              # Entry point
│   └── css/
│       └── game.css           # Custom CSS styles og animationer
├── .gitignore
├── composer.json
└── README.md

## 🎯 Spil Mekanik

### Scoring System
- **Grøn (1-300)**: Meget tæt på svaret
- **Gul (301-1000)**: Tæt på svaret  
- **Orange (1001-3000)**: Moderat tæt
- **Rød (3000+)**: Langt fra svaret
