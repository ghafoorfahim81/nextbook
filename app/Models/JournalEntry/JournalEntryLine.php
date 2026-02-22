<?php

namespace App\Models\JournalEntry;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\JournalEntry\JournalClass;
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
        'journal_class_id',
        'debit',
        'credit',
        'remark',
    ];

    public function journalClass()
    {
        return $this->belongsTo(JournalClass::class, 'journal_class_id');
    }
}
