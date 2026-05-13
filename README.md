# Getting Started with Create React App

This project was bootstrapped with [Create React App](https://github.com/facebook/create-react-app).

## Available Scripts

In the project directory, you can run:

### `npm start`

Runs the app in the development mode.\
Open [http://localhost:3000](http://localhost:3000) to view it in your browser.

The page will reload when you make changes.\
You may also see any lint errors in the console.

### `npm test`

Launches the test runner in the interactive watch mode.\
See the section about [running tests](https://facebook.github.io/create-react-app/docs/running-tests) for more information.

### `npm run build`

Builds the app for production to the `build` folder.\
It correctly bundles React in production mode and optimizes the build for the best performance.

The build is minified and the filenames include the hashes.\
Your app is ready to be deployed!

See the section about [deployment](https://facebook.github.io/create-react-app/docs/deployment) for more information.

### `npm run eject`

**Note: this is a one-way operation. Once you `eject`, you can't go back!**

If you aren't satisfied with the build tool and configuration choices, you can `eject` at any time. This command will remove the single build dependency from your project.

Instead, it will copy all the configuration files and the transitive dependencies (webpack, Babel, ESLint, etc) right into your project so you have full control over them. All of the commands except `eject` will still work, but they will point to the copied scripts so you can tweak them. At this point you're on your own.

You don't have to ever use `eject`. The curated feature set is suitable for small and middle deployments, and you shouldn't feel obligated to use this feature. However we understand that this tool wouldn't be useful if you couldn't customize it when you are ready for it.

## Learn More

You can learn more in the [Create React App documentation](https://facebook.github.io/create-react-app/docs/getting-started).

To learn React, check out the [React documentation](https://reactjs.org/).

### Code Splitting

This section has moved here: [https://facebook.github.io/create-react-app/docs/code-splitting](https://facebook.github.io/create-react-app/docs/code-splitting)

### Analyzing the Bundle Size

This section has moved here: [https://facebook.github.io/create-react-app/docs/analyzing-the-bundle-size](https://facebook.github.io/create-react-app/docs/analyzing-the-bundle-size)

### Making a Progressive Web App

This section has moved here: [https://facebook.github.io/create-react-app/docs/making-a-progressive-web-app](https://facebook.github.io/create-react-app/docs/making-a-progressive-web-app)

### Advanced Configuration

This section has moved here: [https://facebook.github.io/create-react-app/docs/advanced-configuration](https://facebook.github.io/create-react-app/docs/advanced-configuration)

### Deployment

This section has moved here: [https://facebook.github.io/create-react-app/docs/deployment](https://facebook.github.io/create-react-app/docs/deployment)

### `npm run build` fails to minify

This section has moved here: [https://facebook.github.io/create-react-app/docs/troubleshooting#npm-run-build-fails-to-minify](https://facebook.github.io/create-react-app/docs/troubleshooting#npm-run-build-fails-to-minify)

## Backend (PHP + MySQL via XAMPP)

This project now includes a minimal PHP API and MySQL schema for donor registration and appointment booking.

### 1) Start XAMPP

- Start `Apache`
- Start `MySQL`

### 2) Create database/table

- Open `http://localhost/phpmyadmin`
- Import file: `backend/sql/schema.sql`

If the dashboard later reports `tblblood_units` missing, import `backend/sql/recreate_tblblood_units_and_populate.sql` as well.

If phpMyAdmin shows errors like `Table 'blood_donation.tblusers' doesn't exist in engine`, the database metadata is corrupted. In that case:

- Drop the `blood_donation` database completely
- Re-import `backend/sql/schema.sql`

If you use MySQL CLI instead of phpMyAdmin:

```sql
DROP DATABASE IF EXISTS blood_donation;
SOURCE backend/sql/schema.sql;
```

### 3) Verify PHP DB config

- File: `backend/config/db.php`
- Default values are for XAMPP local MySQL:
	- host: `127.0.0.1`
	- db: `blood_donation`
	- user: `root`
	- password: `` (empty)

### 4) Run React app

```bash
npm start
```

By default, donor registration submits to:

`http://localhost/blood_donation/backend/api/register_donor.php`

Book appointment submits to:

`http://localhost/blood_donation/backend/api/book_appointment.php`

If you want another API URL, create `.env` in project root:

```env
REACT_APP_API_URL=http://localhost/your-path/backend/api/register_donor.php
REACT_APP_APPOINTMENT_API_URL=http://localhost/your-path/backend/api/book_appointment.php
REACT_APP_BLOOD_BANKS_API_URL=http://localhost/your-path/backend/api/blood-banks.php
REACT_APP_GOOGLE_MAPS_API_KEY=YOUR_GOOGLE_MAPS_API_KEY
```

## Blood Bank Directory Map Setup (Google Maps)

The map tab in the Blood Bank Directory page requires a browser API key.

1. Open Google Cloud Console and create/select a project.
2. Enable these APIs:
	- Maps JavaScript API
	- Places API (optional, useful for future enhancements)
3. Create an API key.
4. Restrict the key:
	- Application restrictions: HTTP referrers (web sites)
	- Add local refs like `http://localhost:3000/*`
5. Add the key in `.env`:

```env
REACT_APP_GOOGLE_MAPS_API_KEY=PASTE_YOUR_REAL_KEY_HERE
```

Important notes:

- Restart `npm start` after editing `.env` (Create React App only reads env vars at startup).
- The Blood Bank page reads `REACT_APP_GOOGLE_MAPS_API_KEY`.
- `REACT_APP_GOOGLE_MAPS_KEY` is also accepted as a fallback name.

## Email for Doctor Requests (Save then Email)

Doctor request submission now works in this order:

1. Save request in DB (`tblblood_requests`)
2. Send email notification

Default test recipient: `11102000153@rim.edu.bt`

### Configure SMTP (recommended for Gmail)

Set these environment variables for Apache/PHP:

- `BTS_SMTP_HOST` (example: `smtp.gmail.com`)
- `BTS_SMTP_PORT` (example: `587`)
- `BTS_SMTP_ENCRYPTION` (`tls` or `ssl`)
- `BTS_SMTP_USER` (your Gmail address)
- `BTS_SMTP_PASS` (Gmail App Password)
- `BTS_MAIL_FROM` (from email)
- `BTS_MAIL_FROM_NAME` (from name)
- `BTS_TEST_NOTIFY_EMAIL` (default test recipient, optional)

For local XAMPP setup, you can also edit:

- `backend/config/mail.local.php`

This file is read automatically by the mailer when environment variables are not available.

If SMTP is not configured, backend falls back to PHP `mail()` (less reliable in local Windows/XAMPP).

### Test email endpoint

API: `POST /backend/api/test_email.php`

Sample JSON payload:

```json
{
	"recipient": "11102000153@rim.edu.bt",
	"hospitalName": "JDWNRH",
	"bloodType": "A+"
}
```

### Check email setup endpoint

API: `GET /backend/api/email_setup_status.php`

Returns whether email is ready and which keys are missing (`BTS_SMTP_USER`, `BTS_SMTP_PASS`, `BTS_MAIL_FROM`).

### Debug checklist when email is not received

1. Verify Gmail App Password is correct.
2. Check spam/junk folder for the recipient email.
3. Check API response fields: `email.sent`, `email.transport`, `email.error`.
4. Check Apache/PHP error logs in XAMPP.

## Real-Life Blood Workflow (Implemented)

The backend now supports a production-style workflow for donation testing, stock movement, and request processing.

### 1) Run migration for existing databases

Import this SQL file in phpMyAdmin:

- `backend/sql/migrate_real_life_workflow.sql`

This adds:

- `tbldonations`
- `tbldonation_tests`
- `tblstock_ledger`
- `tblrequest_status_logs`

And normalizes old request statuses (`Cross-match*`) to the new status names.

### 2) Request Status Flow

- `Pending -> Approved -> Cross-Matching -> Matched -> Issued`
- Rejection path is supported from active processing states.

### 3) New/Updated APIs

- `POST /backend/api/process_blood_request.php`
	- Actions: `approve`, `reject`, `start_crossmatch`
- `POST /backend/api/record_crossmatch.php`
	- Records result (`Compatible`, `Incompatible`, `Pending`) and transitions request status.
- `POST /backend/api/issue_blood_unit.php`
	- Only issues from `Matched`, checks stock, deducts inventory, writes issue log and stock ledger.

Donation + testing workflow APIs:

- `POST /backend/api/record_donation.php`
- `POST /backend/api/record_donation_test.php`
- `POST /backend/api/add_donation_to_stock.php`

Safe donation units are the only units that can be moved into stock.

### 4) Staff UI Behavior

Staff dashboard action buttons now render by request status:

- `Pending`: Approve, Reject
- `Approved`: Start Cross-Match, Reject
- `Cross-Matching`: Mark Matched, Mark Incompatible
- `Matched`: Issue Blood
- Final states: no further action

