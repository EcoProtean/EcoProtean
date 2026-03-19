#Updated Project Structure 03/19/2026 11:26PM

ecoprotean/
├── admin/
│   └── index.php
│
├── api/
│   ├── locations.php
│   ├── recommendations.php
│   └── simulate.php
│
├── assets/
│   ├── css/
│   │   ├── global.css          ← root style.css
│   │   ├── admin.css           ← admin/style.css
│   │   ├── login.css           ← auth/login-style.css
│   │   ├── signup.css          ← auth/styles.css
│   │   ├── management.css      ← management/style.css
│   │   ├── about.css           ← WebApp/About/style.css
│   │   ├── riskmap.css         ← WebApp/RiskMap/style.css
│   │   └── seedlings.css       ← extracted from inline styles
│   ├── js/
│   │   ├── admin.js            ← admin/script.js
│   │   ├── login.js            ← auth/login-script.js
│   │   ├── signup.js           ← auth/script.js
│   │   ├── management.js       ← management/script.js
│   │   ├── services.js         ← WebApp/RiskMap/services.js
│   │   └── seedlings.js        ← WebApp/Seedlings/seedlings.js
│   ├── images/
│   │   ├── logo.png            ← Photo logo/EcoProtean logo.png
│   │   ├── background.jpg      ← Photo logo/background.jpg
│   │   ├── exit.png            ← Photo logo/exit.png
│   │   ├── person-icon.webp    ← WebApp/Photo logo/Person-Icon.webp
│   │   └── seedlings/
│   │       └── (your 10 images here)
│   └── data/
│       ├── services.json       ← WebApp/RiskMap/services.json
│       └── seedlings.json      ← WebApp/Seedlings/seedlings.json
│
├── auth/
│   ├── login.php
│   ├── logout.php
│   └── signup.php
│
├── config/
│   └── config.php              ← root config.php (PHASE 2 security move)
│
├── db/
│   └── database.sql            ← root database.sql (PHASE 2 security move)
│
├── management/
│   └── index.php
│
├── webapp/
│   ├── about/
│   │   └── index.php
│   ├── riskmap/
│   │   └── index.php
│   └── seedlings/
│       └── index.html
│
├── .htaccess                   ← NEW (Phase 2 security)
└── index.php
