<?php
require_once __DIR__ . '/Model.php';
final class Role extends Model { protected const TABLE = 'roles'; protected const FILLABLE = ['name', 'display_name', 'description']; }
