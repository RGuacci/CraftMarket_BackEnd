# Satisfy API

Satisfy è un piccolo e-commerce dove gli utenti possono acquistare uno o più articoli o, su richiesta, diventare venditori e cominciare a vendere.

Questo progetto è l'API Laravel di un'applicazione React che ho sviluppato personalmente:
👉 [Satisfy (front end)](https://github.com/RGuacci/Satisfy)

## Funzionalità

- Login e registrazione con cookie di sessione, senza token salvati nel browser (**Fortify + Sanctum**)
- Gestione di permessi e ruoli degli utenti: user, seller, admin (**Laravel Policies**)
- CRUD completo dei prodotti, con caricamento e modifica delle immagini
- Un carrello per aggiungere o rimuovere articoli da acquistare
- Possibilità di acquistare uno o più articoli e visualizzarli nella propria pagina profilo (Il pagamento è simulato ed ha sempre successo)
- Possibilità di chiedere agli admin di diventare venditori tramite un form
- Diversi test strutturati e divisi per tipologia, pronti per essere lanciati (**Pest**) 

## Perché l'ho costruito

Satisfy è il mio primo vero progetto indipendente da quando ho finito il bootcamp **Hackademy di Aulab** e, come tale, ha portato diverse sfide sul tavolo che ho dovuto affrontare.

Per la prima volta ho dovuto pensare a tutto senza qualcuno a darmi delle dritte: tutte le decisioni, dalla più piccola alla più grande, sono ricadute su di me. Più volte sono stato costretto a cambiare il codice per decisioni non proprio ottimali prese in precedenza. Ad esempio, nella parte finale del lavoro, dopo il deploy, ho dovuto fare parecchio per rendere le immagini persistenti: all'inizio costruivo l'URL delle immagini direttamente dal front end, quindi ho dovuto modificare la logica in Laravel e poi riscrivere gran parte del codice del front end ovunque ci fossero delle immagini. Questo mi ha insegnato perchè le immagini hanno bisogno di un servizio esterno e perchè non è corretto che sia il frontend a gestirle.

Per quanto riguarda la struttura, ho deciso di creare un progetto con front end e back end separati per vedere e studiare meglio il flusso e come i diversi pezzi comunicano tra loro. Questa scelta, soprattutto all'inizio, mi ha creato diverse difficoltà e mi ha fatto rendere conto di quanto lavoro Laravel nasconde dietro le quinte quando si lavora con le Blade o, in generale, con il front end sullo stesso dominio.

## Stack

- **PHP 8.3+** (8.5 nell'immagine Docker) / **Laravel 13**
- **Laravel Fortify** per login, registrazione e logout
- **Laravel Sanctum** per l'autenticazione della SPA tramite cookie di sessione
- **PostgreSQL** come database
- **Cloudflare R2** per le immagini dei prodotti, tramite il driver S3 di Laravel (`league/flysystem-aws-s3-v3`)
- **Pest** per i test
- **Docker** e Docker Compose per l'ambiente locale e per il deploy

## Deploy

È la prima volta che porto online un progetto con front end e back end separati, e molti di questi servizi li ho usati qui per la prima volta. Ogni pezzo vive su un servizio diverso:

| Pezzo | Servizio | Perché |
|---|---|---|
| API Laravel | **Render** (container Docker) | Supporta il deploy da `Dockerfile` e ha un piano gratuito |
| Database | **Neon** (PostgreSQL) | PostgreSQL gestito con piano gratuito, senza dover amministrare un server |
| Immagini | **Cloudflare R2** | Il disco del container su Render non è persistente: a ogni nuovo deploy i file caricati andrebbero persi. Salvando le immagini su R2 sopravvivono ai deploy, e l'API restituisce al front end l'URL completo |
| Front end React | **Vercel** | Gestito con un piano gratuito e redeploy automatico dopo eventuali modifiche |

<!-- da riscrivere -->.

## Avvio in locale

L'unico requisito è avere **Docker** e **Docker Compose** installati: PHP, Composer e PostgreSQL girano dentro i container.

1. Clona la repo:
   ```bash
   git clone https://github.com/RGuacci/Satisfy_API
   cd Satisfy_API
   ```
2. Crea il file `.env`. I valori di default funzionano già con Docker, non serve modificarli:
   ```bash
   cp .env.example .env
   ```
3. Costruisci e avvia i container:
   ```bash
   docker compose up -d --build
   ```
4. Genera la chiave dell'applicazione:
   ```bash
   docker compose exec app php artisan key:generate
   ```
5. Crea le tabelle e i dati iniziali:
   ```bash
   docker compose exec app php artisan migrate --seed
   ```
6. Crea il link simbolico per le immagini:
   ```bash
   docker compose exec app php artisan storage:link
   ```

L'API risponde su `http://localhost:8000`. Per usare l'app completa, avvia anche il [front end](https://github.com/RGuacci/Satisfy).

### Test

L'immagine Docker installa solo le dipendenze di produzione, quindi per i test bisogna prima installare anche quelle di sviluppo (basta farlo una volta):

```bash
docker compose exec app composer install
docker compose exec app php artisan test
```

I test usano un database SQLite in memoria, quindi non toccano i dati del database locale.
 

