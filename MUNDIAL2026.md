# MUNDIAL2026 — Specyfikacja techniczna aplikacji do typowania

> Plik przeznaczony dla Claude Code. Zawiera pełną architekturę, zasady biznesowe, schemat DB, podział na warstwy (Controller → Request → Service → Model) oraz wszystkie kluczowe decyzje projektowe.

---

## 1. Opis aplikacji

Prywatna aplikacja do typowania meczów Mistrzostw Świata 2026 (FIFA World Cup, USA/Meksyk/Kanada) dla zamkniętej grupy znajomych. Uczestnicy logują się PINem, obstawiają wyniki meczów i śledzą ranking. Opcjonalna pula pieniężna (10 zł do szuflady). Panel administracyjny w Filament 3.

---

## 2. Stack technologiczny

- **Backend:** Laravel 11, PHP 8.3
- **Frontend:** Vue 3 + Inertia.js + Tailwind CSS
- **Admin panel:** Filament 3
- **Baza danych:** MySQL 8
- **Cache/Queue:** Redis (lub database driver na shared hostingu)
- **SMS:** SMSAPI.pl (powiadomienia o nadchodzących meczach)
- **Dane sportowe:** API-Football v3 (api-sports.io), `league=1`, `season=2026`
- **Auth:** Laravel Auth guard z własnym modelem `Participant` (logowanie: imię + PIN)

---

## 3. Zasady biznesowe

### 3.1 Uczestnicy i dostęp
- Rejestracja **tylko przez admina** — brak publicznej rejestracji
- Admin tworzy uczestnika w panelu Filament, ustawia PIN (4–6 cyfr)
- PIN jest wysyłany uczestnikowi ręcznie (WhatsApp/SMS)
- Opcjonalnie: przy tworzeniu uczestnika z numerem telefonu system wysyła SMS powitalny z PINem

### 3.2 Pula pieniężna
- Udział w typowaniu jest **darmowy**
- Kto chce grać o kasę — przynosi **10 zł do szuflady** (fizycznie)
- Admin oznacza wpłatę w panelu (`paid_entry = true`)
- Aplikacja nie obsługuje płatności online

### 3.3 Obstawianie
- **Faza grupowa:** tylko typ **1X2** (1 = wygrana gospodarza, X = remis, 2 = wygrana gościa)
- **Faza pucharowa** (od 1/8 finału wzwyż): typ 1X2 + opcjonalnie **dokładny wynik liczbowy** (np. 2:1)
- Wyniki liczbowe są **niejawne** (nie widać cudzych)
- Typy 1X2 są **jawne** dla wszystkich uczestników
- Deadline obstawiania: **1 godzina przed pierwszym gwizdkiem** meczu
- Po deadlinie edycja i nowe typy są zablokowane
- Nie trzeba obstawiać każdego meczu

### 3.4 Eliminacja
- **3 nieoobstawione mecze** (gdzie mecz już się zakończył) = **automatyczna eliminacja** uczestnika
- Wyeliminowany uczestnik nadal widzi aplikację i może typować (dla zabawy), ale **nie figuruje w oficjalnym rankingu**
- Eliminacja jest nieodwracalna (chyba że admin ręcznie cofnie w Filament)

### 3.5 Punktacja
- Trafiony typ 1X2 = **1 punkt**
- Błędny typ 1X2 = **0 punktów**
- Dokładny wynik liczbowy = **0 punktów** (tylko tiebreaker)
- Nieoobstawiony mecz = **0 punktów** (+ licznik do eliminacji)

### 3.6 Tiebreaker (przy równej liczbie punktów)
1. Liczba trafionych dokładnych wyników liczbowych (faza pucharowa)
2. Liczba trafionych 1X2 w fazie grupowej
3. Trafiony król strzelców (tak/nie) — podany przed turniejem, niejawny
4. Losowanie 🎲

### 3.7 Powiadomienia SMS
- Automatyczny SMS do uczestników którzy **nie obstawili** meczu, wysyłany **1 godzinę przed** kickoff
- Uczestnik może wyłączyć powiadomienia (`sms_notifications = false`)
- SMS tylko gdy uczestnik ma uzupełniony numer telefonu
- Provider: SMSAPI.pl, nadawca: `MUNDIAL26`

---

## 4. Schemat bazy danych

### Tabela: `participants`
```sql
id                  BIGINT PK AUTO_INCREMENT
name                VARCHAR(100) NOT NULL         -- "Marcin K."
pin                 VARCHAR(255) NOT NULL          -- bcrypt hash
phone               VARCHAR(20) NULL              -- "+48500123456", do SMS
is_admin            BOOLEAN DEFAULT 0
paid_entry          BOOLEAN DEFAULT 0             -- wpłacił 10 zł
eliminated          BOOLEAN DEFAULT 0             -- 3 nieoobstawione mecze
sms_notifications   BOOLEAN DEFAULT 1
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

### Tabela: `world_matches`
```sql
id                  BIGINT PK AUTO_INCREMENT
api_fixture_id      INT UNIQUE NOT NULL           -- ID z API-Football
home_team           VARCHAR(100) NOT NULL
away_team           VARCHAR(100) NOT NULL
home_team_flag      VARCHAR(10) NULL              -- emoji "🇵🇱"
away_team_flag      VARCHAR(10) NULL
kickoff_at          TIMESTAMP NOT NULL            -- UTC
stage               ENUM('group','r32','r16','qf','sf','final') NOT NULL
group_name          VARCHAR(5) NULL               -- 'A'..'L', NULL w fazie pucharowej
status              ENUM('scheduled','finished') DEFAULT 'scheduled'
score_home          TINYINT UNSIGNED NULL         -- NULL przed meczem
score_away          TINYINT UNSIGNED NULL
reminder_sent       BOOLEAN DEFAULT 0             -- SMS wysłany
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Uwaga:** Brak statusu `live` — aplikacja nie robi live pollingu. Wyniki pobierane jednorazowo po zakończeniu meczu.

### Tabela: `bets`
```sql
id                  BIGINT PK AUTO_INCREMENT
participant_id      BIGINT FK → participants.id
match_id            BIGINT FK → world_matches.id
prediction_1x2      ENUM('1','X','2') NOT NULL
predicted_home      TINYINT UNSIGNED NULL         -- tylko faza pucharowa
predicted_away      TINYINT UNSIGNED NULL         -- tylko faza pucharowa
is_correct          BOOLEAN NULL                  -- NULL=nierozstrzygnięty, TRUE/FALSE po meczu
created_at          TIMESTAMP
updated_at          TIMESTAMP
UNIQUE(participant_id, match_id)
```

### Tabela: `tiebreaker_picks`
```sql
id                  BIGINT PK AUTO_INCREMENT
participant_id      BIGINT FK UNIQUE → participants.id
top_scorer_name     VARCHAR(100) NOT NULL         -- "Erling Haaland"
submitted_at        TIMESTAMP NOT NULL
created_at          TIMESTAMP
updated_at          TIMESTAMP
```
**Constraint:** `submitted_at` musi być przed startem turnieju (2026-06-11 18:00 UTC). Wymuszane w walidacji, nie DB constraint.

### Tabela: `group_standings` (cache tabel API)
```sql
id                  BIGINT PK AUTO_INCREMENT
group_name          VARCHAR(5) NOT NULL           -- 'A'..'L'
api_team_id         INT NOT NULL
team_name           VARCHAR(100) NOT NULL
team_flag           VARCHAR(10) NULL
position            TINYINT NOT NULL
played              TINYINT DEFAULT 0
won                 TINYINT DEFAULT 0
drawn               TINYINT DEFAULT 0
lost                TINYINT DEFAULT 0
goals_for           TINYINT DEFAULT 0
goals_against       TINYINT DEFAULT 0
points              TINYINT DEFAULT 0
synced_at           TIMESTAMP
```

---

## 5. Modele Eloquent

### `app/Models/Participant.php`
- Extends `Authenticatable`
- Guard: `participant` (osobny guard w `config/auth.php`)
- Relacje: `hasMany(Bet::class)`, `hasOne(TiebreakerPick::class)`
- Cast: `pin` → hashed (Laravel 11: `protected function pin(): Attribute`)
- Metody:
  - `pointsTotal(): int` — suma punktów ze skończonych meczów
  - `missedMatchesCount(): int` — liczba nieoobstawionych skończonych meczów
  - `exactScoreCount(): int` — tiebreaker #1
  - `groupCorrectCount(): int` — tiebreaker #2
  - `scorerCorrect(): bool` — tiebreaker #3

### `app/Models/WorldMatch.php`
- Nazwa klasy: `WorldMatch` (unikamy kolizji z reserved words)
- Relacje: `hasMany(Bet::class)`
- Metody:
  - `result1x2(): ?string` — zwraca '1'/'X'/'2' lub null jeśli nie skończony
  - `canBet(): bool` — `status === 'scheduled' && now() < kickoff_at->subHour()`
  - `isKnockout(): bool` — `stage !== 'group'`
- Scopes:
  - `scopeUpcoming($q)` — scheduled, kickoff w przyszłości
  - `scopeFinished($q)` — status finished
  - `scopePendingResults($q)` — scheduled, kickoff_at <= now()->subMinutes(105), kickoff_at >= now()->subHours(5)

### `app/Models/Bet.php`
- Relacje: `belongsTo(Participant::class)`, `belongsTo(WorldMatch::class)`
- Metody:
  - `points(): int` — 1 jeśli is_correct, 0 jeśli nie

### `app/Models/TiebreakerPick.php`
- Relacje: `belongsTo(Participant::class)`

### `app/Models/GroupStanding.php`
- Scope: `scopeForGroup($q, string $group)`

---

## 6. Architektura — warstwy

```
HTTP Request
    ↓
Route (routes/web.php)
    ↓
Controller (app/Http/Controllers/)
    ↓ (walidacja przez FormRequest)
Form Request (app/Http/Requests/)
    ↓
Service (app/Services/)
    ↓
Model / Eloquent (app/Models/)
    ↓
Database
```

**Zasady:**
- Controller jest cienki — tylko: pobierz dane z requesta, przekaż do serwisu, zwróć odpowiedź
- Logika biznesowa WYŁĄCZNIE w serwisach
- Walidacja WYŁĄCZNIE w FormRequest klasach
- Modele zawierają tylko relacje, scopy, casty i proste metody pomocnicze

---

## 7. Struktura plików

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── LoginController.php
│   │   ├── BetController.php
│   │   ├── RankingController.php
│   │   ├── MatchController.php
│   │   └── TiebreakerController.php
│   ├── Requests/
│   │   ├── Auth/
│   │   │   └── LoginRequest.php
│   │   ├── StoreBetRequest.php
│   │   ├── UpdateBetRequest.php
│   │   └── StoreTiebreakerRequest.php
│   └── Middleware/
│       └── EnsureNotEliminated.php       ← opcjonalny, info-only
├── Models/
│   ├── Participant.php
│   ├── WorldMatch.php
│   ├── Bet.php
│   ├── TiebreakerPick.php
│   └── GroupStanding.php
├── Services/
│   ├── BetService.php
│   ├── RankingService.php
│   ├── EliminationService.php
│   ├── FootballApiService.php
│   └── SmsService.php
├── Jobs/
│   ├── FetchFinishedMatchResultsJob.php
│   ├── SyncStandingsJob.php
│   ├── SyncTopScorersJob.php
│   ├── SendMatchRemindersJob.php
│   └── CheckEliminationsJob.php
├── Notifications/
│   ├── MatchReminderNotification.php
│   └── WelcomeNotification.php
├── Channels/
│   └── SmsChannel.php
├── Console/
│   └── Commands/
│       ├── ImportFixturesCommand.php     -- mundial:import-fixtures
│       ├── ImportTeamsCommand.php        -- mundial:import-teams
│       └── AddParticipantCommand.php     -- mundial:add-participant
└── Filament/
    └── Resources/
        ├── ParticipantResource.php
        ├── ParticipantResource/
        │   └── Pages/
        │       ├── ListParticipants.php
        │       ├── CreateParticipant.php
        │       └── EditParticipant.php
        ├── WorldMatchResource.php
        └── WorldMatchResource/
            └── Pages/
                ├── ListWorldMatches.php
                └── ViewMatchBets.php

resources/js/
├── Pages/
│   ├── Auth/
│   │   └── Login.vue
│   ├── Bets/
│   │   └── Index.vue                     ← lista meczów do obstawienia
│   ├── Ranking/
│   │   └── Index.vue
│   └── Standings/
│       └── Index.vue
└── Components/
    ├── MatchCard.vue
    ├── BetForm.vue
    └── RankingTable.vue
```

---

## 8. Kontrolery i serwisy — szczegóły

### `LoginController`
```
GET  /login         → show()    → Inertia: Auth/Login
POST /login         → login()   → LoginRequest → Auth::attempt(['name', 'pin'])
POST /logout        → logout()
```

### `LoginRequest`
```php
rules: [
    'name' => ['required', 'string', 'max:100'],
    'pin'  => ['required', 'digits_between:4,6'],
]
```

### `BetController`
```
GET  /bets          → index()   → lista meczów pogrupowana wg daty + etapu
POST /bets          → store()   → StoreBetRequest → BetService::placeBet()
PUT  /bets/{bet}    → update()  → UpdateBetRequest → BetService::updateBet()
```

### `StoreBetRequest`
```php
rules: [
    'match_id'       => ['required', 'exists:world_matches,id'],
    'prediction_1x2' => ['required', 'in:1,X,2'],
    'predicted_home' => ['nullable', 'integer', 'min:0', 'max:20',
                         'required_with:predicted_away',
                         // tylko faza pucharowa — walidacja w after()
                        ],
    'predicted_away' => ['nullable', 'integer', 'min:0', 'max:20',
                         'required_with:predicted_home'],
]
// after() hook: sprawdź canBet() dla danego meczu
// after() hook: jeśli mecz jest grupowy, wyzeruj predicted_home/away
```

### `UpdateBetRequest`
```php
// Identyczne reguły jak StoreBetRequest
// Dodatkowa autoryzacja: bet należy do auth()->user()
```

### `BetService`
```php
placeBet(Participant $participant, array $data): Bet
    // 1. Pobierz mecz, sprawdź canBet()
    // 2. Jeśli mecz grupowy → wyzeruj predicted_home/away
    // 3. Utwórz lub zaktualizuj Bet (updateOrCreate)
    // 4. Zwróć Bet

updateBet(Bet $bet, array $data): Bet
    // 1. Sprawdź canBet() dla bet->match
    // 2. Zaktualizuj pola
    // 3. Zwróć Bet

resolveBets(WorldMatch $match): void
    // Wywoływane po pobraniu wyniku meczu
    // Dla każdego Bet do tego meczu:
    //   bet.is_correct = (bet.prediction_1x2 === match.result1x2())
    // Wywołaj EliminationService::checkAll()
```

### `EliminationService`
```php
checkAll(): void
    // Dla każdego nieusuniętego uczestnika:
    //   missed = WorldMatch::finished()->count() - participant.bets()->count()
    //   jeśli missed >= 3 → participant.eliminated = true

checkParticipant(Participant $p): void
    // Sprawdź jeden konkretny uczestnik
```

### `RankingService`
```php
getRanking(): Collection
    // Zwraca kolekcję posortowaną wg:
    // 1. points DESC
    // 2. exact_scores DESC
    // 3. group_correct DESC
    // 4. scorer_correct DESC
    // Zawiera tylko nieusuniętych uczestników
    // Wyeliminowani na końcu jako osobna sekcja

getFullRanking(): Collection
    // Jak wyżej ale z wyeliminowanymi
```

### `FootballApiService`
```php
// Base URL: https://v3.football.api-sports.io
// Auth header: x-apisports-key
// League ID: 1 (FIFA World Cup)
// Season: 2026

getAllFixtures(): array
    // GET /fixtures?league=1&season=2026
    // Cache: 7 dni

getFixturesByDate(string $date): array
    // GET /fixtures?league=1&season=2026&date=YYYY-MM-DD
    // Cache: 15 minut
    // Używane przez FetchFinishedMatchResultsJob

getStandings(): array
    // GET /standings?league=1&season=2026
    // Cache: 60 minut

getTopScorers(): array
    // GET /players/topscorers?league=1&season=2026
    // Cache: 6 godzin

// Zabezpieczenie limitu:
// Każde wywołanie loguje x-ratelimit-requests-remaining
// Jeśli remaining < 10 → log warning, zwróć dane z cache
```

### `SmsService`
```php
send(string $phone, string $message): bool
    // POST https://api.smsapi.pl/sms.do
    // Token: config('services.smsapi.token')
    // From: config('services.smsapi.sender', 'MUNDIAL26')
    // Normalizacja numeru: +48500123456 → 48500123456

normalizePhone(string $phone): string
```

---

## 9. Jobs i Scheduler

### `FetchFinishedMatchResultsJob`
- Uruchamiany: co 15 minut
- Logika:
  1. Znajdź mecze gdzie `status=scheduled` AND `kickoff_at <= now()-105min` AND `kickoff_at >= now()-5h`
  2. Jeśli brak takich meczów → zakończ (0 requestów do API)
  3. Pobierz wyniki przez `FootballApiService::getFixturesByDate(today)`
  4. Zaktualizuj `score_home`, `score_away`, `status='finished'`
  5. Wywołaj `BetService::resolveBets($match)` dla każdego zaktualizowanego meczu
  6. Wywołaj `EliminationService::checkAll()`

### `SyncStandingsJob`
- Uruchamiany: co godzinę
- Truncate `group_standings` + insert świeżych danych z API

### `SyncTopScorersJob`
- Uruchamiany: dwa razy dziennie (08:00, 20:00)
- Zapisuje do cache `mundial.topscorers`

### `SendMatchRemindersJob`
- Uruchamiany: co minutę (sprawdza warunek)
- Logika:
  1. Znajdź mecze gdzie `kickoff_at BETWEEN now()+58min AND now()+62min` AND `reminder_sent=false`
  2. Dla każdego meczu: znajdź uczestników którzy NIE mają betu na ten mecz AND `sms_notifications=true` AND `phone IS NOT NULL` AND `eliminated=false`
  3. Wyślij `MatchReminderNotification`
  4. Ustaw `reminder_sent=true`

### `CheckEliminationsJob`
- Uruchamiany: codziennie o 23:00
- Wywołuje `EliminationService::checkAll()`

### Scheduler (routes/console.php)
```php
Schedule::job(FetchFinishedMatchResultsJob::class)->everyFifteenMinutes();
Schedule::job(SyncStandingsJob::class)->hourly();
Schedule::job(SyncTopScorersJob::class)->twiceDaily(8, 20);
Schedule::job(SendMatchRemindersJob::class)->everyMinute();
Schedule::job(CheckEliminationsJob::class)->dailyAt('23:00');
```

---

## 10. Artisan Commands

### `mundial:import-fixtures`
```
php artisan mundial:import-fixtures
```
- Wywołuje `FootballApiService::getAllFixtures()`
- Mapuje dane API → `WorldMatch`
- Używa `updateOrCreate(['api_fixture_id' => ...], [...])`
- Mapowanie stage z API:
  - `Group Stage` → `group`
  - `Round of 32` → `r32`
  - `Round of 16` → `r16`
  - `Quarter-finals` → `qf`
  - `Semi-finals` → `sf`
  - `Final` → `final`
- Wypisuje ile meczów zaimportowano/zaktualizowano
- Bezpieczny do wielokrotnego uruchomienia

### `mundial:add-participant`
```
php artisan mundial:add-participant "Marcin K." --pin=4821 --phone=+48500123456
```
- Tworzy uczestnika
- Jeśli podano `--phone` → wysyła `WelcomeNotification` z PINem przez SMS

---

## 11. Trasy (routes/web.php)

```php
// Gość
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

// Zalogowany uczestnik
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', fn() => redirect()->route('bets.index'));

    // Obstawianie
    Route::get('/bets', [BetController::class, 'index'])->name('bets.index');
    Route::post('/bets', [BetController::class, 'store'])->name('bets.store');
    Route::put('/bets/{bet}', [BetController::class, 'update'])->name('bets.update');

    // Ranking
    Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

    // Tabele grup
    Route::get('/standings', [StandingsController::class, 'index'])->name('standings.index');

    // Tiebreaker — król strzelców (deadline: start turnieju)
    Route::get('/tiebreaker', [TiebreakerController::class, 'show'])->name('tiebreaker.show');
    Route::post('/tiebreaker', [TiebreakerController::class, 'store'])->name('tiebreaker.store');
});
```

---

## 12. Auth Guard

W `config/auth.php` dodaj guard `participant`:
```php
'guards' => [
    'web' => [
        'driver'   => 'session',
        'provider' => 'participants',
    ],
],
'providers' => [
    'participants' => [
        'driver' => 'eloquent',
        'model'  => App\Models\Participant::class,
    ],
],
```
Używamy domyślnego guarda `web` z modelem `Participant` zamiast `User`.

---

## 13. Filament Panel Admina

### Konfiguracja
```php
// app/Providers/Filament/AdminPanelProvider.php
// path: /admin
// guard: web (Participant z is_admin=true)
// Autoryzacja: canAccessPanel() → $participant->is_admin
```

### `ParticipantResource`
Kolumny tabeli:
- Imię (sortowanie, wyszukiwanie)
- Punkty — obliczone z `pointsTotal()`, badge zielony
- Obstawione mecze — counts('bets')
- Pominięte mecze — obliczone, czerwony jeśli >= 3
- Wpłata 💰 — toggle `paid_entry`
- Eliminacja — boolean, czerwony jeśli true
- Telefon — ukryty domyślnie (toggleable)

Akcje:
- Edit (PIN, telefon, sms_notifications, paid_entry, eliminated)
- Przycisk "Wyślij PIN SMS" — jeśli ma telefon
- Toggle paid_entry

Filtry:
- Tylko wpłacili
- Tylko aktywni (nie wyeliminowani)

### `WorldMatchResource`
Kolumny tabeli:
- Data/czas (strefa: Europe/Warsaw)
- Grupa / Etap
- Mecz (home vs away)
- Wynik (score_home:score_away, badge zielony po zakończeniu)
- Liczba typów
- Status

Akcja: kliknięcie wiersza → `ViewMatchBets`

### `ViewMatchBets` (custom Page)
Zawiera:
- Nagłówek: nazwa meczu, data, wynik końcowy
- Tabela wszystkich betów:
  - Imię uczestnika
  - Typ 1X2
  - Dokładny wynik (tylko faza pucharowa)
  - ✅/❌ czy trafił (null jeśli mecz nie skończony)
  - 💰 czy wpłacił
- Sekcja "Nie obstawili" — lista uczestników bez betu + 🚫 jeśli wyeliminowany

---

## 14. Frontend Vue (Inertia)

### `Bets/Index.vue` — główny widok
- Lista meczów pogrupowana wg daty
- Każdy mecz: flagi, nazwy drużyn, data/czas, czy można jeszcze obstawiać
- Jeśli deadline minął: pokazuje swój typ (lub "-")
- Jeśli mecz skończony: pokazuje wynik + czy trafił
- Wszystkie typy 1X2 uczestników (jawne) pokazane w tabeli po meczu
- **Wyniki liczbowe NIE są pokazywane** (niejawne, tylko admin widzi)

### `Ranking/Index.vue`
- Tabela rankingowa tylko nieusuniętych
- Kolumny: pozycja, imię, punkty, obstawione/skończone, pominięte
- 💰 ikona jeśli paid_entry
- Wyeliminowani w osobnej sekcji poniżej

### `Standings/Index.vue`
- 12 tabel grupowych A–L
- Dane z `group_standings`

---

## 15. Powiadomienia SMS

### `WelcomeNotification`
```
"🏆 Hej! Dołączyłeś do typowania Mundial 2026.
Zaloguj się: {APP_URL}
Twój PIN: {PIN}"
```

### `MatchReminderNotification`
```
"⚽ Mundial: {home} vs {away} dziś o {HH:MM}.
Masz jeszcze 1h na obstawienie!
{APP_URL}/bets"
```

### `SmsChannel`
Własny kanał powiadomień Laravel. Rejestrowany w `AppServiceProvider::boot()`:
```php
Notification::extend('sms', fn($app) => new SmsChannel($app->make(SmsService::class)));
```

---

## 16. Konfiguracja zewnętrznych serwisów

### `config/services.php`
```php
'apifootball' => [
    'key' => env('APIFOOTBALL_KEY'),
],
'smsapi' => [
    'token'  => env('SMSAPI_TOKEN'),
    'sender' => env('SMSAPI_SENDER', 'MUNDIAL26'),
],
```

### `.env` (wymagane klucze)
```
APIFOOTBALL_KEY=
SMSAPI_TOKEN=
SMSAPI_SENDER=MUNDIAL26
APP_TIMEZONE=UTC         # daty w DB zawsze UTC, konwersja na Europe/Warsaw w widokach
```

---

## 17. Budżet API-Football (free plan: 100 req/dzień)

| Operacja | Częstość | Req/dzień |
|---|---|---|
| Import fixtures | raz na start | ~2 |
| Standings | co godzinę | 24 |
| Top scorers | 2× dziennie | 2 |
| Wyniki meczów | 1 req/dzień z meczami | max 1 |
| **Razem (maks)** | | **~29/100** ✅ |

**Strategia cache:**
- Wszystkie odpowiedzi API cachowane przez `Cache::remember()`
- TTL dopasowany do częstości zmian (standings: 60min, fixtures: 7dni)
- Sprawdzanie nagłówka `x-ratelimit-requests-remaining` po każdym requeście
- Jeśli pozostało < 10 requestów → log warning + zwróć dane z cache

---

## 18. Kluczowe decyzje i ograniczenia

1. **Brak live pollingu** — wyniki pobierane jednorazowo po zakończeniu meczu (~105 min po kickoff). Celowo, dla mieszczenia się w darmowym planie API.

2. **Brak public registration** — tylko admin tworzy uczestników.

3. **Timezone** — wszystkie timestampy w DB jako UTC. Konwersja na `Europe/Warsaw` tylko w widokach i notyfikacjach.

4. **Model Participant zamiast User** — guard `web` przepiąć na `participants` provider. Nie używamy tabeli `users`.

5. **Wyniki liczbowe niejawne** — `predicted_home`/`predicted_away` nigdy nie są zwracane w Inertia props dla innych uczestników. Widzi je tylko admin w Filament.

6. **Eliminacja nieodwracalna przez system** — tylko admin może ręcznie cofnąć (`eliminated = false` w Filament edit).

7. **Dokładny wynik = tiebreaker, nie punkty** — `is_correct` na modelu `Bet` odnosi się wyłącznie do 1X2. Exact score przechowywany ale nie punktowany.

8. **Faza pucharowa** — mecze ze `stage IN ('r32','r16','qf','sf','final')` pokazują pole na dokładny wynik w `BetForm.vue`.

---

## 19. Kolejność implementacji (sugerowana)

1. Migracje + modele + relacje
2. Auth guard + `LoginController` + `Login.vue`
3. `mundial:import-fixtures` command + `FootballApiService`
4. `BetController` + `BetService` + `StoreBetRequest` + `Bets/Index.vue`
5. `FetchFinishedMatchResultsJob` + `BetService::resolveBets()`
6. `EliminationService` + `CheckEliminationsJob`
7. `RankingService` + `RankingController` + `Ranking/Index.vue`
8. `SyncStandingsJob` + `Standings/Index.vue`
9. `SmsService` + `SmsChannel` + `SendMatchRemindersJob`
10. Filament: `ParticipantResource` + `WorldMatchResource` + `ViewMatchBets`
11. `TiebreakerController` + `StoreTiebreakerRequest`
12. Testy + seed danych deweloperskich
