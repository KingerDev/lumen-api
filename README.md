# Lumen API

Laravel 13 backend pre denníkovú appku. Drží zápisky, šablóny a metadáta médií;
samotné fotky a videá žijú v Cloudflare R2.

## Prečo takto

Fotky nikdy neprechádzajú cez PHP. Server iba podpíše URL a appka nahráva
priamo do R2:

```
1. appka   →  POST /api/media/presign  { mime }
2. Laravel →  Storage::disk('r2')->temporaryUploadUrl(...)   →  { url, key, headers }
3. appka   →  PUT <url> + bajty                              →  R2
4. appka   →  POST /api/media  { key, entryId, width, height }
5. Laravel →  overí, že objekt v R2 existuje, a uloží riadok
```

Vďaka tomu nerieši `upload_max_filesize` ani timeouty, VPS neplatí za prenos
a R2 nemá egress poplatky. Kľúče k bucketu neopustia server.

## Endpointy

| Metóda | Cesta | Popis |
|---|---|---|
| `POST` | `/api/login` | e-mail + heslo → Sanctum token |
| `GET` | `/api/me` | kto som |
| `POST` | `/api/logout` | zruší token tohto zariadenia |
| `GET` | `/api/sync?since=` | všetko zmenené od času (vrátane zmazaní) |
| `POST` | `/api/sync` | dávkový zápis lokálnych zmien |
| `GET` | `/api/entries` | zoznam; `search`, `from`, `to`, `per_page` |
| `POST/PATCH/DELETE` | `/api/entries/{id}` | CRUD |
| `GET/POST/PATCH/DELETE` | `/api/templates` | šablóny |
| `POST` | `/api/media/presign` | podpísaná URL na upload |
| `POST` | `/api/media` | potvrdenie uploadu |
| `POST` | `/api/media/urls` | dávka čerstvých URL na čítanie |
| `DELETE` | `/api/media/{id}` | zmaže objekt aj riadok |

Registračný endpoint zámerne neexistuje — účet sa vyrába cez artisan.

## Synchronizácia

Appka je local-first: píše sa offline, server je zdieľaná kópia. Konflikty sa
riešia last-write-wins podľa `updated_at`. Pri jednom používateľovi na jednom či
dvoch zariadeniach je súbežná úprava toho istého zápisku taká zriedkavá, že
čokoľvek zložitejšie by stálo viac, než ušetrí.

Mazanie je soft delete a `GET /api/sync` vracia aj zmazané riadky s `deletedAt` —
inak by sa zmazanie na druhé zariadenie nikdy nedostalo.

## Čas

`entry_date` drží pravý UTC okamih, `entry_utc_offset` posun v minútach, pri
ktorom zápisok vznikol. Bez toho druhého sa nedá zrekonštruovať hodina na
hodinách — zápisok napísaný o 21:30 v Bratislave musí čítať 21:30 aj po
prílete do iného pásma. API preto vracia dátum v pôvodnom offsete.

## Lokálne spustenie

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # lokálne stačí SQLite
php artisan migrate
php artisan lumen:user              # vytvorí účet + naseeduje šablóny
php artisan serve
```

## Import z Day One

Spúšťaj na serveri, nie z telefónu — export má ~1,9 GB v 751 súboroch.

```bash
php artisan lumen:import-dayone /srv/import --email=ja@example.sk
```

kde `/srv/import` obsahuje `Journal.csv`, `photos/` a `videos/` z rozbaleného
exportu. Voľby: `--skip-media` (len texty), `--dry-run`, `--template=`.

Príkaz je idempotentný — zápisky sa kľúčujú na Day One `uuid`, médiá na `md5`,
takže prerušený beh sa dá spustiť znova a nadviaže tam, kde skončil.

Overené na reálnom exporte: 939 zápiskov, z toho 929 rozdelených do sekcií
podľa šablóny, 906 s polohou, 893 s počasím.

## Nasadenie na Coolify

1. **Postgres** ako resource v tom istom projekte. `DB_HOST` je názov tej
   služby vo vnútornej sieti, nie `localhost`.
2. **Application** z tohto repa, build pack **Dockerfile**, base directory `server`.
3. Premenné z `.env.example` do **Environment Variables** — vrátane `APP_KEY`
   (`php artisan key:generate --show`) a R2 kľúčov. Coolify ich drží zašifrované.
4. Port **8080**, doménu a Let's Encrypt rieši Coolify.
5. Po prvom deployi raz: `php artisan lumen:user`.

Migrácie beží image sám (`AUTORUN_LARAVEL_MIGRATION=true`).

### R2 — na čom sa dá stroskotať

Premenné sú `R2_*`, nie `AWS_*` — stock Laravel `.env` obsahuje
`AWS_DEFAULT_REGION=us-east-1` a `AWS_USE_PATH_STYLE_ENDPOINT=false`, ktoré by
ticho prebili hodnoty potrebné pre R2 a rozbili každú podpísanú URL.

```env
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=lumen-media
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
```

Región `auto` a path-style adresovanie sú natvrdo v `config/filesystems.php`.
