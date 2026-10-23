# OmniMart – Full-Stack Multi-Vendor E-Commerce Platform (PHP + MySQL)

Dynamic storefront, admin panel, vendor panel and REST API. All content (products, categories, banners, offers, coupons, theme, SEO, homepage sections) is controlled from the database.

## 1. Setup (2 minutes)
**XAMPP / WAMP / Laragon**
1. Copy the `omnimart` folder into `htdocs` (or `www`).
2. Start Apache + MySQL. Open phpMyAdmin → *Import* → choose `database.sql` (it creates the `omnimart` database).
3. Check DB credentials in `config.php` (defaults: host 127.0.0.1, user `root`, empty password).
4. Open `http://localhost/omnimart/`  (needs `mod_rewrite` for pretty URLs; if it is off, Admin → Settings → SEO → untick *SEO-friendly URLs*).

**PHP built-in server (no Apache needed)**
```
mysql -u root < database.sql
php -S localhost:8000 router.php
```
Requires PHP 8.0+ with `pdo_mysql`, `mbstring`, `fileinfo`.

## 2. Demo logins
| Role | Login | Password |
|---|---|---|
| Admin (`/admin/`) | `admin` | `admin123` |
| Customer | `customer@demo.com` | `password123` |
| Vendor | `vendor1@demo.com` (TechNest) / `vendor2@demo.com` (StyleHub) | `password123` |

Coupons: `WELCOME10` (10 %, min ₹500), `FLAT200` (₹200 off, min ₹1500), `OLD50` (expired – shows validation).
Sandbox payments: Stripe test card `4242 4242 4242 4242` (any future expiry, any CVC); Razorpay UPI `success@razorpay`.

## 3. Feature map (matches the marking scheme)
| Criterion | Where |
|---|---|
| Responsive, dynamic frontend | `index.php` (DB banners slider, categories, featured, new arrivals, promo cards, recently viewed), `search.php` (price/category/rating/sort filters + pagination), mobile-first `assets/css/style.css`, multi-currency + 3 languages (header selectors) |
| Cart & checkout | `cart.php` (add/update/remove, coupons, stock limits), `product.php` variations (size/colour/stock/price adjust), `checkout.php` (validation, COD / Stripe sandbox / Razorpay sandbox, stock locked in a DB transaction, confirmation page, email, printable `invoice.php`) |
| Auth, profile, wishlist | `signup.php` (customer or vendor), `login.php` (sessions, remember-me cookie, throttling), `logout.php`, `profile.php` (orders, cancel pending order, wishlist, edit profile, change password) |
| Contact & reviews | `contact.php` (DB + email, honeypot), reviews/ratings and product Q&A on `product.php` |
| Admin panel | `admin/` dashboard analytics, products (CRUD, image upload, variations, CSV import/export, featured), categories, vendors (approve/suspend/add), orders (status workflow + stock restore on cancel + email), users/inbox (block users, reply to messages, moderate reviews, answer Q&A, newsletter list), banners, settings (site, theme colours, dark mode, SEO, homepage sections, currencies) |
| Vendor panel | `vendor/` dashboard (sales, best sellers, low stock, Q&A), own-product CRUD + CSV, own orders with per-item status updates |
| Coupons | `admin/coupons.php` – percent/fixed, expiry, min order, usage limit, enable/disable, usage counter |
| Reports | `admin/reports.php` – date range, daily revenue chart, by vendor / category / product / status / payment, CSV export, print-to-PDF |
| Security & API | see §4 and §5 |
| Bonus | wishlist, recently viewed, product CSV import/export, SEO URLs + `sitemap.xml` + `robots.txt` + JSON-LD, newsletter, product Q&A, sandbox gateways, multi-language, invoices, dark mode |

## 4. Security
`password_hash/password_verify` · PDO prepared statements everywhere · global CSRF token on every POST · output escaping (`e()`) · role checks (customer / vendor / admin) with ownership checks on every vendor action · `session_regenerate_id` on login · login throttling · hashed remember-me and API tokens · validated image uploads (MIME check, random names, PHP execution disabled in `uploads/`) · server-side validation of all forms.

## 5. REST API (JSON, Bearer token)
```
POST /api/token.php        {"email","password"}  or  {"username","password"} (admin)  -> {"token": "..."}
GET  /api/categories.php
GET  /api/products.php?q=&category=&page=&limit=      (public)     GET /api/products.php?id=5
POST|PUT|DELETE /api/products.php   (vendor/admin token; vendors only touch their own products)
GET  /api/orders.php[?id=]   customer = own orders, vendor = orders with their items, admin = all
POST /api/orders.php         {"items":[{"product_id":9,"quantity":1,"variation_ids":[]}],"shipping":{name,phone,address,city,postal},"payment_method":"cod","coupon":"FLAT200"}
PATCH /api/orders.php        {"id":3,"status":"Shipped"}  (admin)
```
Send the token as `Authorization: Bearer <token>`.

## 6. Notes
* Stripe/Razorpay run as **sandbox simulations** (test card / UPI id) so the project works without API keys; `process_payment()` in `includes/functions.php` is the single place to swap in the real SDK calls.
* Emails use PHP `mail()` and are also written to `storage/mail.log`, so you can see them on a local server without SMTP.
* Prices are stored in INR; the currency selector converts for display (rates editable in Admin → Settings → Currencies).
* "PDF" reports/invoices use the browser's *Print → Save as PDF*; CSV export is built in.
