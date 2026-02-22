<?php

namespace App\Models\JournalEntry;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
class JournalEntryLine extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'journal_entry_lines';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'ledger_id',
        'debit',
        'credit',
        'remark',
    ];
}
