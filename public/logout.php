<?php
require_once __DIR__ . '/../config/db.php';

ensure_session_security(false);
logout_user();
redirect(app_url('login.php'));
