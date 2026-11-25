# Postafiókok és e-mail

Egy postafiók (`Mailbox`, `MailboxController`, `resources/js/pages/Mailboxes`) IMAP-ról letölti a leveleket, átadja a hozzárendelt agentnek, az agent válaszát pedig SMTP-n küldi.

## Mailbox mezők (`app/Models/Mailbox.php`)

`name`, `email`, `from_name`, `username`, `password` (titkosítva tárolt, `encrypted` cast, a modell `hidden` listáján; szerkesztésnél üres jelszó = nem változik), `imap_host/port/encryption/folder`, `smtp_host/port/encryption`, `agent_id`, `project_id`, `is_active`, `auto_read`, `auto_reply`, `last_fetched_at`, `last_uid`.

`MailEncryption` (`app/Enums/MailEncryption.php`): `ssl`, `tls`, `none`. Validáció: `StoreMailboxRequest` / `UpdateMailboxRequest`, `app/Concerns/MailboxValidationRules.php`.

## Levelek letöltése

- `php artisan mailboxes:fetch {mailbox?}` – egy (név vagy azonosító) vagy minden aktív postafiók; naponta ütemezve (`DEVELOPER_TOOL_MAILBOX_FETCH_TIME`, `…_TIMEZONE`). A felületen: `POST mailboxes/{mailbox}/fetch`.
- `MailboxService::fetchIncoming` (alapból max. 100 levél): az utolsó ismert UID után tölt (`last_uid`), a csatolmányokat a `local` lemezre menti. Az `ImapMailFetcher` PHP `imap` kiterjesztést használ (a futtató környezetben elérhetőnek kell lennie).
- `auto_read` bekapcsolva és ha van agent + projekt (`canAgentRead`), az új leveleket az agent azonnal megkapja.

Modellek: `IncomingEmail` (`read_at`, `prompt_run_id`, kereshető), `IncomingEmailAttachment` (letöltés mindig `attachment`-ként: `GET mailboxes/{m}/emails/{e}/attachments/{a}`), `OutgoingEmail`.

## Levél → agent → válasz

1. **Olvasás**: `POST mailboxes/{m}/emails/{e}/read` (vagy `auto_read`) → `MailboxService::sendToAgent`: az agent a postafiók projektjén promptként kapja a levelet (fejlécek, csatolmány-útvonalak, szöveg max. 50 000 karakter), a levél olvasottá válik és a futáshoz kötődik. Már olvasott levél nem adható át újra.
2. **Válasz**: az agent a `mail` MCP szerver `reply-email` eszközével válaszol (levelenként egyszer). `MailboxService::reply` `OutgoingEmail`-t hoz létre `Re:` tárggyal.
   - `auto_reply` bekapcsolva: azonnal kimegy.
   - különben `draft`: `POST mailboxes/{m}/replies/{o}/send` jóváhagyja és elküldi, `DELETE …/replies/{o}` elveti (elküldött válasz nem törölhető).
3. `OutgoingEmailStatus`: `draft`, `sent`, `failed`; sikertelen küldés `failed` + `error`, újra küldhető (`isSendable`: minden, ami nem `sent`).

A törölt levél csak a helyi példányt törli, az IMAP szerveren marad (`IncomingEmailController::destroy`).

## Seeder és jelszavak

`MailboxSeeder` a `config/developer-tool.php` `mailbox_passwords` listájából (e-mail → jelszó az env-ből: `MAILBOX_AIAGENT1_PASSWORD`, `…2…`, `…3…`) és `MAILBOX_SERVER_HOST`-ból hoz létre postafiókokat (IMAP 993 / SMTP 465, SSL). Host vagy jelszó hiányában az adott postafiókot kihagyja. A jelszavak értékét ne írd a dokumentációba.
