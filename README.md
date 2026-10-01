# WhatsApp Terminbuchung — Marketing Demo

Standalone showcase for **automated WhatsApp-style appointment booking** (Friseur / lokaler Dienstleister).

Sales line: *„Das habe ich — ich baue es für dich.“*

Built for Huriye / TeamTrack (team-track.de). No Meta, Google, Make, or n8n credentials required.

---

## What this demos

1. **WhatsApp-like chat UI** (German) — service → day → time → name/phone → confirmation  
2. **Live availability** from salon opening hours + bookings already stored in this browser  
3. **Double-booking prevention** (overlap check before a slot is saved)  
4. **Simulated calendar + WhatsApp calls** in the browser (no credentials)  
5. **Simulated 24h reminder** as a system message in the chat  
6. **Editable salon config** — `assets/js/salon.js`

---

## Requirements

A current browser (Chrome, Edge, Firefox). No PHP, no terminal, no local server.

---

## Run

Double-click **`index.html`**.

The chat starts immediately. Bookings stay in this browser (`localStorage`, key `whatsapp-termin-demo.bookings`). Restarting the chat keeps them, so a taken slot stays taken. To wipe demo bookings, clear site data for the opened file.

`config/salon.json`, `api.php`, `lib/`, and `stubs/` are leftover server files. The showcase does not call them.

---

## Customize the sample salon

Edit **`assets/js/salon.js`** (`window.SALON_CONFIG`):

| Field | Purpose |
|--------|---------|
| `name`, `tagline`, `phone` | Salon identity shown in UI |
| `welcomeMessage` | First bot message |
| `services[]` | Bookable services (id, name, duration, price) |
| `openingHours` | `mon`–`sun` (`null` = closed) |
| `closedDates` | Extra closed days (`YYYY-MM-DD`) |
| `slotIntervalMinutes` | Slot grid (default 30) |
| `bookingHorizonDays` | How far ahead customers can book |
| `reminderHoursBefore` | Simulated reminder offset (default 24) |

No code changes needed for a typical sales demo tweak.

---

## Project layout

```
whatsapp-termin-demo/
├── index.html                 # Chat UI — double-click this
├── assets/css/app.css
├── assets/js/salon.js         # Editable salon sample
├── assets/js/booking.js       # Slots, overlap guard, localStorage
├── assets/js/app.js           # Chat flow
├── config/salon.json          # Unused by the showcase (PHP leftover)
├── api.php, lib/, stubs/      # Unused by the showcase (PHP leftover)
└── README.md
```

---

## Copy to Windows

Copy the whole folder, then double-click `index.html`. No command prompt.

---

## Production note (not in this demo)

Real roll-out would wire:

- **WhatsApp Business Cloud API** / 360dialog / Wati (templates for confirmation + reminder)  
- **Google Calendar** or Cal.com (create/block events)  
- Optional cron/queue for the 24h reminder  

The PHP files under `stubs/` sketch those call shapes for a later server version. This showcase does not run them.

---

## License / use

Internal sales & portfolio demo. Not affiliated with Meta/WhatsApp.
