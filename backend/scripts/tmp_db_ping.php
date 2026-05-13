<?php
$tests = ['localhost', '127.0.0.1'];
foreach ($tests as $h) {
  $t = microtime(true);
  try {
    $pdo = new PDO('mysql:host=' . $h . ';dbname=blood_donation;charset=utf8mb4', 'root', '', [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_TIMEOUT => 5,
    ]);
    $ms = round((microtime(true) - $t) * 1000);
    echo $h . ' ok ' . $ms . "ms\n";
  } catch (Throwable $e) {
    $ms = round((microtime(true) - $t) * 1000);
    echo $h . ' fail ' . $ms . 'ms ' . $e->getMessage() . "\n";
  }
}
