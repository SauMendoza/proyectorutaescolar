<?php

//"importamos" el archivo autoload.php
require_once __DIR__ . '/../vendor/autoload.php';

//definimos que usamos la clase Dotenv
use Dotenv\Dotenv;

//createInmutable prepara el lector del archivo .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

//Hora de Colombia: las asistencias guardan la hora y la fecha de hoy con esta zona
date_default_timezone_set('America/Bogota');

//Iniciamos la sesión para que $_SESSION se conserve entre páginas
session_start();
