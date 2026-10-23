<?php
require_once __DIR__ . '/../db.php'; require_admin();
$panel = 'admin'; $scopeVendor = null;
require __DIR__ . '/../includes/products_crud.php';   // handles POST/export (redirects) before any output
$title = 'Products';
require __DIR__ . '/../includes/admin_header.php';
render_products($panel, $scopeVendor, $self, $cats, $vendorsList);
require __DIR__ . '/../includes/admin_footer.php';
