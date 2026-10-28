Store Order & Inventory Mini-System

A Laravel 10 application for managing a product catalog, recording customer orders, maintaining inventory, and retrieving customer order history.

Features
Product catalog with unique product codes, unit prices, tax percentages, and stock quantities.
Customer records with unique email addresses.
Order creation with one or more product lines.
Server-side validation and automatic subtotal, tax, and grand-total calculation.
Transactional stock deduction with database row locking to prevent overselling.
Customer order history lookup by email.
Configurable low-stock reporting.
Queued order-confirmation simulation using Laravel logging.
Product and customer factories, seeders, and automated feature tests.
Requirements
PHP 8.1 or newer
Composer
MySQL 8 with InnoDB tables
A supported Node.js and npm version
Laravel 10
Installation
Clone the repository and enter the project directory.
Install PHP dependencies:

composer install

Copy .env.example to .env and configure the database:

cp .env.example .env

Generate the application key:

php artisan key:generate

Create the database and configure DB_DATABASE, DB_USERNAME, and DB_PASSWORD.
Add config LOW_STOCK_THRESHOLD=10
Run migrations and seed sample data:

php artisan migrate --seed

Configure the queue:

QUEUE_CONNECTION=database

Create the jobs table if the migration is not already present:

php artisan queue:table

php artisan migrate

Start the Laravel server:

php artisan serve

Start the queue worker in another terminal:

php artisan queue:work

Build frontend assets if a frontend is included:

npm run build

API Endpoints
Method	Endpoint	Description
POST	/api/orders	Create an order
GET	/api/customers/orders?email=...	Retrieve customer order history
GET	/api/products/low-stock	List products below the default stock threshold
GET	/api/products/low-stock?threshold=5	List products below a custom threshold

Send Accept: application/json with requests. Order creation accepts customer name and email, plus an array of product IDs and quantities.

Order Calculation

Prices are taken from the database rather than trusted from the request.

For each order line:

Line subtotal = unit price × quantity
Line tax = rounded line subtotal × tax percentage ÷ 100
Line total = line subtotal + line tax

The order subtotal and tax are sums of the respective line amounts. The grand total is subtotal plus tax. Currency amounts are rounded to two decimal places per line.

Inventory Concurrency

Order creation runs in a database transaction. Relevant product rows are locked using SELECT ... FOR UPDATE through Eloquent's lockForUpdate().

Stock validation, order creation, order-item creation, and stock deduction occur in the same transaction. A failed stock check rolls back the transaction.

MySQL with InnoDB is the target database for concurrency correctness. SQLite-based tests are not considered proof of row-lock behavior.

Queue Processing

A SendOrderConfirmation job is dispatched after a successful order transaction. The job writes a simulated confirmation entry to the Laravel application log. No SMTP server is required.

Run a worker using php artisan queue:work.

Assumptions and Design Decisions
Customer identity: Email address uniquely identifies a customer. An existing customer's name is updated if a new order supplies a different name for the same email.
Duplicate product lines: Duplicate product IDs in a single order request are rejected.
Tax calculation: Tax is calculated per order line and rounded to two decimal places. Order totals are sums of rounded line totals.
Pricing: Product prices and tax rates are read from the database. Order items store snapshots to preserve historical pricing.
Low-stock threshold: The default threshold is configurable through LOW_STOCK_THRESHOLD, with a default of 10. Products with stock strictly below the threshold are returned.
Queue behavior: Confirmation is simulated through application logging; real email delivery is outside the scope of this assignment.
Authentication: The core API is intended for a trusted demo environment unless authentication middleware is explicitly configured.
Currency: Monetary values use two decimal places. Currency is assumed to be a single configured business currency; multi-currency support is out of scope.
Inventory adjustments: Stock is reduced only when an order succeeds. Returns, cancellations, and restocking workflows are out of scope.
Concurrency: Production concurrency verification must use a database engine supporting row-level locks and independent concurrent transactions.
Testing

Run automated tests using:

php artisan test

The test suite covers successful order creation, totals, inventory deduction, insufficient stock, queue dispatch, customer history, and low-stock reporting.

A separate MySQL concurrency integration test should verify that simultaneous requests cannot sell the same final unit.

Limitations
No payment processing.
No real email delivery.
No order cancellation or returns.
No multi-currency support.
Authentication and authorization must be configured before exposing the API publicly.