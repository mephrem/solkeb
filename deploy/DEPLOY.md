# Installing Solkeb Hotel on the hotel PC

One Windows PC in the hotel runs the system. Staff use it from that PC and from the other
computers, tablets and phones on the hotel Wi-Fi, by opening **http://SERVER-IP** in a browser.
After the steps below it **starts by itself whenever the PC is switched on**, even after a power cut,
without anyone logging in.

---

## 1. Prepare the PC

- A reliable PC, kept on all the time, ideally on a **UPS** (battery) so short power cuts don't stop it.
- In the BIOS, set **"Restore on AC power loss" = Power On**, so the PC turns itself back on after a long cut.
- Windows: Settings → System → Power → **Sleep: Never**. Set Windows Update **active hours** to the
  busy front-desk hours so it doesn't restart in the middle of a check-in.
- Connect it to the hotel network by **cable** if possible. Ask whoever manages the router to give it a
  **fixed (reserved) IP address**, e.g. 192.168.1.20. This is the address staff will type.
- Make the hotel network a **Private network** on this PC (Settings → Network & internet → your network).
- A second disk or USB disk for the backups (drive **D:** by default, see step 7).

## 2. Install the software

1. Install **Laragon Full** (https://laragon.org/download). It includes PHP, MySQL, Apache, Composer and Git.
2. Open Laragon → **Start All** once (it creates its settings), then **Stop All**.
3. Check the versions: Laragon → menu → PHP → version **8.3 or newer**; MySQL **8.x**.

## 3. Get the system

Open **Laragon → Terminal** and run:

```bash
cd C:\laragon\www
git clone https://github.com/mephrem/solkeb.git
cd solkeb
composer install --no-dev --optimize-autoloader
```

The repository is private: Git asks you to sign in to GitHub the first time.

## 4. Database and settings

1. Start MySQL (Laragon → Start All). Laragon → **Database** (or HeidiSQL) → create a database
   **solkeb** (utf8mb4_unicode_ci). Set a **password for the MySQL root user**, or create a user just for
   the system, and write it down somewhere safe.
2. Create the settings file:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. Open `.env` in Notepad and set:

   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=http://192.168.1.20        (the PC's reserved address)
   DB_DATABASE=solkeb
   DB_USERNAME=root                   (or the user you created)
   DB_PASSWORD=...                    (the MySQL password)
   ```

4. Create the tables and the admin account, then speed things up:

   ```bash
   php artisan migrate --seed --force
   php artisan optimize
   ```

5. **Moving data from the training/test PC instead?** Back it up there with `deploy\backup.bat`, then on
   this PC import the `solkeb.sql` file into the empty **solkeb** database (HeidiSQL → File → Run SQL file)
   and copy the `id-photos` folder into `storage\app\private`. Skip `--seed` in step 4.

## 5. Start automatically at boot

In Laragon click **Stop All**, then in File Explorer go to `C:\laragon\www\solkeb\deploy`, right-click
**install-services.bat** → **Run as administrator**. It:

- makes the hotel system the page shown at **http://SERVER-IP**
- registers MySQL and Apache as Windows **services** that start at boot and restart if they stop
- opens port 80 for the other hotel computers (private network only)
- schedules the **nightly backup** at 23:30

From then on **don't click "Start All" in Laragon** — the services are already running. (Laragon's
Terminal can still be used for commands.) Restart the PC once and check the system comes up by itself.

## 6. First login and setup

Open **http://localhost** on the PC (or http://SERVER-IP elsewhere).

1. Log in as **admin@solkebhotel.com** with the password from `database/seeders/DatabaseSeeder.php`,
   then **change it immediately** (click your name at the bottom of the sidebar).
2. **Hotel settings**: name, address, phone, TIN, VAT, check-out time **11:00**, half day **14:30 / 50%**.
3. **Rooms & rates**: room types and prices; add the rooms (Import makes this quick).
4. **Bank accounts**: every account and wallet; tick the one printed on invoices.
5. **Organisations**: the companies and NGOs, and which may pay on credit.
6. **Staff accounts**: one account per person with the right role. Nobody shares a login.

## 7. Backups (do not skip)

- `deploy\backup.bat` saves the **database**, the **guests' ID photos** and the **settings file** into
  `D:\SolkebBackups\<date>` every night and keeps 30 days. Change `BACKUP_DIR` at the top of the file if
  the backup disk is not D:.
- Double-click it once now and check a folder appears.
- Once a week, copy the latest backup folder to a **USB stick kept outside the hotel** (fire, theft).
- The backup contains guests' ID photos and the database password: keep it private.

## 8. Updating to a new version

When a new version is pushed to GitHub, on the hotel PC double-click **deploy\update.bat** (at a quiet
moment). It backs up first, then updates the code and the database and clears the caches.

## 9. Opening it on the other computers and phones

- Type **http://SERVER-IP** (e.g. http://192.168.1.20) in the browser and add it to favourites / the home
  screen.
- The system is for the hotel network only. **Do not** set up port forwarding on the router to reach it
  from the internet.

## If something goes wrong

| Problem | What to do |
|---|---|
| Page doesn't open on other computers | Is the server PC on? Same Wi-Fi? Try http://localhost on the server itself. Check its IP with `ipconfig`. |
| Page doesn't open on the server either | Services → check **SolkebMySQL** and **SolkebApache** are *Running*; right-click → Start. |
| "Server Error" page | Look at `storage\logs\laravel.log`; after changing `.env` run `php artisan optimize`. |
| Need to restore a backup | Import `solkeb.sql` into the database with HeidiSQL and copy `id-photos` back into `storage\app\private`. |
