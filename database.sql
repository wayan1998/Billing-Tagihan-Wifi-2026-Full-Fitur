-- DWINET.ID Tagihan WiFi - skema MySQL / MariaDB
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS settings(k VARCHAR(40) PRIMARY KEY, v TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS users(
  id VARCHAR(20) PRIMARY KEY, name VARCHAR(80) NOT NULL, username VARCHAR(40) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL, role ENUM('admin','penagih') NOT NULL DEFAULT 'penagih',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS packages(
  id VARCHAR(20) PRIMARY KEY, name VARCHAR(80) NOT NULL, price INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS customers(
  id VARCHAR(20) PRIMARY KEY, code VARCHAR(20) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL DEFAULT '', address VARCHAR(255) NOT NULL DEFAULT '',
  package_id VARCHAR(20) NULL, status ENUM('aktif','isolir','nonaktif') NOT NULL DEFAULT 'aktif',
  due_day TINYINT NOT NULL DEFAULT 5, pppoe_user VARCHAR(60) NOT NULL DEFAULT '',
  registered_at DATE NULL, collector_id VARCHAR(20) NULL, KEY(status),
  FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE SET NULL,
  FOREIGN KEY(collector_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoices(
  id VARCHAR(20) PRIMARY KEY, invoice_no VARCHAR(30) NOT NULL, customer_id VARCHAR(20) NOT NULL,
  period CHAR(7) NOT NULL, due_date DATE NOT NULL, amount INT NOT NULL DEFAULT 0, discount INT NOT NULL DEFAULT 0,
  status ENUM('belum','lunas') NOT NULL DEFAULT 'belum', penalty INT NOT NULL DEFAULT 0,
  pay_method VARCHAR(20) NULL, paid_at DATE NULL, note VARCHAR(255) NULL, collector_name VARCHAR(80) NULL,
  cash_received INT NOT NULL DEFAULT 0, UNIQUE KEY(customer_id,period), KEY(period), KEY(status,due_date),
  FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS expenses(
  id VARCHAR(20) PRIMARY KEY, spent_at DATE NOT NULL, description VARCHAR(255) NOT NULL, amount INT NOT NULL DEFAULT 0,
  KEY(spent_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO settings(k,v) VALUES('nama','DWINET.ID'),('alamat',''),('wa',''),
('rek','Transfer: BCA 0000000000 a.n. DWINET.ID'),('denda','2000'),('maxd','30'),('pw','58'),('seq','0'),
('tpl','Yth. {nama}, tagihan WiFi DWINET.ID periode {periode} sebesar {total} jatuh tempo {tempo}. {rek}. Terima kasih.');
INSERT IGNORE INTO packages(id,name,price) VALUES('p1','Hemat 10 Mbps',150000),('p2','Keluarga 20 Mbps',200000),('p3','Gamer 50 Mbps',350000);
