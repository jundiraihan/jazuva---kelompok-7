<?php
require_once __DIR__ . '/Model.php';
final class Recipe extends Model { protected const TABLE = 'recipes'; protected const FILLABLE = ['menu_id', 'notes']; }
