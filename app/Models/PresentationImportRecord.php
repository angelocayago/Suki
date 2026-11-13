<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Internal provenance for the explicitly-run local presentation dataset. */
class PresentationImportRecord extends Model
{
    protected $fillable = ['batch_key', 'record_key', 'model_type', 'model_id'];
}
