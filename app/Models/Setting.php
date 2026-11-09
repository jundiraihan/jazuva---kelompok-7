<?php
require_once __DIR__ . '/Model.php';
final class Setting extends Model { protected const TABLE = 'settings'; protected const FILLABLE = ['setting_key', 'setting_value', 'description']; }
