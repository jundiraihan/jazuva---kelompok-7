<?php
require_once __DIR__ . '/Model.php';
final class RecipeDetail extends Model { protected const TABLE = 'recipe_details'; protected const FILLABLE = ['recipe_id', 'ingredient_id', 'amount_required', 'unit']; }
