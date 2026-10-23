-- OmniMart database: schema + demo data.  Import with:  mysql -u root < database.sql
CREATE DATABASE IF NOT EXISTS omnimart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE omnimart;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS product_questions, newsletter, wishlist, settings, currencies, banners, admin, feedback, reviews, coupons, order_items, orders, product_variations, products, categories, vendors, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('customer','vendor','admin') NOT NULL DEFAULT 'customer',
  phone VARCHAR(20) NULL,
  address TEXT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  remember_token VARCHAR(64) NULL,
  api_token VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE vendors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  status ENUM('pending','approved','suspended') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  sale_price DECIMAL(10,2) NULL,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(255) NULL,
  category_id INT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX (status), INDEX (is_featured), FULLTEXT KEY ft_products (name, description)
) ENGINE=InnoDB;

CREATE TABLE product_variations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  attribute VARCHAR(50) NOT NULL,
  value VARCHAR(80) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  price_adjust DECIMAL(10,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(10,2) NOT NULL,
  coupon_code VARCHAR(40) NULL,
  status ENUM('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  payment_method VARCHAR(20) NOT NULL DEFAULT 'cod',
  payment_status ENUM('unpaid','paid','failed') NOT NULL DEFAULT 'unpaid',
  payment_ref VARCHAR(60) NULL,
  ship_name VARCHAR(100), ship_phone VARCHAR(20), ship_address TEXT, ship_city VARCHAR(80), ship_postal VARCHAR(12),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (status), INDEX (created_at)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NULL,
  vendor_id INT NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  variation VARCHAR(200) NULL,
  variation_ids VARCHAR(60) NULL,
  quantity INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  item_status ENUM('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  discount_type ENUM('percent','fixed') NOT NULL,
  value DECIMAL(10,2) NOT NULL,
  expiry DATE NULL,
  min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
  usage_limit INT NOT NULL DEFAULT 0,
  used_count INT NOT NULL DEFAULT 0,
  status TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_review (product_id, user_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL, message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  api_token VARCHAR(64) NULL
) ENGINE=InnoDB;

CREATE TABLE banners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL, subtitle VARCHAR(255) NULL, image VARCHAR(255) NULL,
  link VARCHAR(255) NULL, button_text VARCHAR(40) NULL,
  position ENUM('hero','promo') NOT NULL DEFAULT 'hero',
  sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE settings (skey VARCHAR(60) PRIMARY KEY, svalue TEXT) ENGINE=InnoDB;
CREATE TABLE currencies (code CHAR(3) PRIMARY KEY, name VARCHAR(40), symbol VARCHAR(5), rate DECIMAL(12,6) NOT NULL) ENGINE=InnoDB;

CREATE TABLE wishlist (
  user_id INT NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE newsletter (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(150) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;

CREATE TABLE product_questions (
  id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_id INT NOT NULL,
  question TEXT NOT NULL, answer TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

/* ================= DEMO DATA ================= */
-- Admin login: admin / admin123      Demo users (password: password123): customer@demo.com, vendor1@demo.com, vendor2@demo.com
INSERT INTO admin (username, password) VALUES ('admin', '$2y$10$lRsQtLzpMPe9HY3n7CLN3ut3BENcApL93H9Ei/P.gqtM9OmPLwQjK');

INSERT INTO users (id, name, email, password, role, phone, address) VALUES
 (1, 'Demo Customer', 'customer@demo.com', '$2y$10$hZTnKqQIBRwUfpnvvRewxunSA87Qav0G6zboXvf0J45P3UKSdJY8q', 'customer', '9876543210', '12 MG Road, Ahmedabad'),
 (2, 'Aarav Shah', 'vendor1@demo.com', '$2y$10$hZTnKqQIBRwUfpnvvRewxunSA87Qav0G6zboXvf0J45P3UKSdJY8q', 'vendor', '9811111111', NULL),
 (3, 'Meera Patel', 'vendor2@demo.com', '$2y$10$hZTnKqQIBRwUfpnvvRewxunSA87Qav0G6zboXvf0J45P3UKSdJY8q', 'vendor', '9822222222', NULL),
 (4, 'Riya Mehta', 'riya@demo.com', '$2y$10$hZTnKqQIBRwUfpnvvRewxunSA87Qav0G6zboXvf0J45P3UKSdJY8q', 'customer', '9833333333', NULL);

INSERT INTO vendors (id, user_id, name, description, status) VALUES
 (1, 2, 'TechNest', 'Gadgets and accessories you will love.', 'approved'),
 (2, 3, 'StyleHub', 'Trendy fashion and lifestyle products.', 'approved');

INSERT INTO categories (id, name, slug) VALUES
 (1,'Electronics','electronics'),(2,'Fashion','fashion'),(3,'Home & Kitchen','home-kitchen'),(4,'Books','books'),(5,'Sports','sports'),(6,'Beauty','beauty');

INSERT INTO products (id, vendor_id, name, slug, description, price, sale_price, stock, category_id, is_featured) VALUES
 (1,1,'Wireless Bluetooth Headphones','wireless-bluetooth-headphones','Over-ear headphones with 30-hour battery life, deep bass and active noise isolation.',2999,2299,40,1,1),
 (2,1,'Smart Fitness Watch','smart-fitness-watch','Track heart rate, sleep and 20+ sport modes. 7-day battery and water resistant.',4499,3499,25,1,1),
 (3,1,'Portable Power Bank 20000mAh','portable-power-bank-20000mah','Fast-charging 22.5W power bank with dual USB and USB-C ports.',1899,NULL,60,1,0),
 (4,1,'Mechanical Gaming Keyboard','mechanical-gaming-keyboard','RGB backlit mechanical keyboard with hot-swappable blue switches.',3299,2799,18,1,1),
 (5,2,'Classic Cotton T-Shirt','classic-cotton-t-shirt','100% combed cotton, regular fit, pre-shrunk and super soft.',599,449,120,2,1),
 (6,2,'Slim Fit Denim Jeans','slim-fit-denim-jeans','Stretch denim with a modern slim fit. Durable and comfortable all day.',1799,1399,70,2,1),
 (7,2,'Running Sports Shoes','running-sports-shoes','Lightweight breathable mesh shoes with cushioned sole.',2499,1999,45,5,1),
 (8,2,'Stainless Steel Water Bottle','stainless-steel-water-bottle','Double-wall vacuum insulated bottle, keeps drinks cold 24h and hot 12h.',799,NULL,90,3,0),
 (9,2,'Non-stick Cookware Set (5 pcs)','non-stick-cookware-set-5-pcs','Induction-friendly non-stick cookware set with heat-resistant handles.',3499,2799,22,3,1),
 (10,1,'Yoga Mat Anti-Slip 6mm','yoga-mat-anti-slip-6mm','Extra-thick eco-friendly mat with carrying strap.',999,749,80,5,0),
 (11,2,'Vitamin C Face Serum','vitamin-c-face-serum','Brightening serum with 20% Vitamin C and hyaluronic acid.',699,NULL,55,6,0),
 (12,1,'The Psychology of Money (Book)','the-psychology-of-money-book','Timeless lessons on wealth, greed and happiness by Morgan Housel.',399,299,100,4,0);

INSERT INTO product_variations (product_id, attribute, value, stock, price_adjust) VALUES
 (5,'Size','S',30,0),(5,'Size','M',40,0),(5,'Size','L',35,0),(5,'Size','XL',15,50),
 (5,'Color','Black',50,0),(5,'Color','White',40,0),(5,'Color','Navy',30,0),
 (6,'Size','30',20,0),(6,'Size','32',25,0),(6,'Size','34',15,0),(6,'Size','36',10,0),
 (6,'Color','Blue',40,0),(6,'Color','Black',30,0),
 (7,'Size','UK 7',15,0),(7,'Size','UK 8',15,0),(7,'Size','UK 9',15,0),
 (7,'Color','Red',20,0),(7,'Color','Grey',25,0),
 (1,'Color','Black',20,0),(1,'Color','Blue',20,100),
 (2,'Color','Black',15,0),(2,'Color','Silver',10,200),
 (8,'Size','500 ml',40,0),(8,'Size','750 ml',50,150);

INSERT INTO coupons (code, discount_type, value, expiry, min_order, usage_limit) VALUES
 ('WELCOME10','percent',10,DATE_ADD(CURDATE(), INTERVAL 1 YEAR),500,0),
 ('FLAT200','fixed',200,DATE_ADD(CURDATE(), INTERVAL 6 MONTH),1500,100),
 ('OLD50','percent',50,'2020-01-01',0,0);

INSERT INTO banners (title, subtitle, link, button_text, position, sort_order) VALUES
 ('Mega Electronics Sale','Up to 40% off on headphones, watches and more','search.php?category=electronics','Shop Electronics','hero',1),
 ('New Season Fashion','Fresh styles from our top fashion vendors','search.php?category=fashion','Explore Fashion','hero',2),
 ('Home Essentials','Upgrade your kitchen & home today','search.php?category=home-kitchen','Shop Home','hero',3),
 ('Use code WELCOME10','Get 10% off your first order above ₹500','search.php','Start Shopping','promo',1),
 ('Free sandbox payments','Try Stripe / Razorpay test mode at checkout','checkout.php','Checkout now','promo',2);

INSERT INTO currencies (code, name, symbol, rate) VALUES
 ('INR','Indian Rupee','₹',1),('USD','US Dollar','$',0.012),('EUR','Euro','€',0.011),('GBP','British Pound','£',0.0095);

INSERT INTO settings (skey, svalue) VALUES
 ('site_name','OmniMart'),('tagline','Everything you love, from vendors you trust'),
 ('contact_email','support@omnimart.local'),('contact_phone','+91 98765 43210'),('contact_address','Ahmedabad, Gujarat, India'),
 ('footer_text','© OmniMart. A multi-vendor marketplace demo.'),('default_currency','INR'),
 ('theme_primary','#4f46e5'),('theme_accent','#f59e0b'),('theme_dark_mode','0'),
 ('seo_title','OmniMart – Multi-Vendor Online Marketplace'),('seo_description','Shop electronics, fashion, home and more from trusted vendors on OmniMart.'),('seo_keywords','online shopping, marketplace, multi vendor, ecommerce'),
 ('seo_urls','1'),
 ('home_show_categories','1'),('home_show_featured','1'),('home_show_new','1'),('home_show_promo','1'),
 ('home_featured_title','Featured Products'),('home_new_title','New Arrivals'),('home_items_count','8');

INSERT INTO reviews (product_id, user_id, rating, comment) VALUES
 (1,1,5,'Superb sound quality and very comfortable!'),(1,4,4,'Battery lasts really long. Worth the price.'),
 (5,1,5,'Great fit and fabric.'),(7,4,4,'Light and comfy for daily runs.');

INSERT INTO orders (id, user_id, subtotal, discount, total_amount, status, payment_method, payment_status, ship_name, ship_phone, ship_address, ship_city, ship_postal, created_at) VALUES
 (1,1,2748,0,2748,'Delivered','cod','paid','Demo Customer','9876543210','12 MG Road','Ahmedabad','380001',DATE_SUB(NOW(), INTERVAL 6 DAY)),
 (2,4,3499,349.9,3149.1,'Shipped','stripe','paid','Riya Mehta','9833333333','5 Park Street','Surat','395001',DATE_SUB(NOW(), INTERVAL 3 DAY)),
 (3,1,1999,0,1999,'Pending','cod','unpaid','Demo Customer','9876543210','12 MG Road','Ahmedabad','380001',DATE_SUB(NOW(), INTERVAL 1 DAY));
INSERT INTO order_items (order_id, product_id, vendor_id, product_name, variation, quantity, price, item_status) VALUES
 (1,1,1,'Wireless Bluetooth Headphones','Color: Black',1,2299,'Delivered'),(1,5,2,'Classic Cotton T-Shirt','Size: M, Color: Black',1,449,'Delivered'),
 (2,2,1,'Smart Fitness Watch','Color: Black',1,3499,'Shipped'),
 (3,7,2,'Running Sports Shoes','Size: UK 8, Color: Grey',1,1999,'Pending');
