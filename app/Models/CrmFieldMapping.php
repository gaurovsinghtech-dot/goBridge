<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFieldMapping extends Model
{
    use HasFactory;

    protected $table = 'crm_field_mappings';

    protected $fillable = [
        'workspace_id',
        'provider',
        'growbridge_field',
        'crm_field',
        'direction',
        'is_custom',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Default field mappings per provider
     */
    public static function getDefaultMappings(string $provider = 'all'): array
    {
        $base = [
            'first_name' => 'firstname',
            'last_name' => 'lastname',
            'phone' => 'phone',
            'email' => 'email',
            'company' => 'company',
            'lead_source' => 'lead_source',
            'lead_status' => 'status',
            'whatsapp_phone' => 'whatsapp_phone',
            'notes' => 'description',
            'ai_summary' => 'ai_interaction_summary',
        ];

        return match ($provider) {
            'hubspot' => array_merge($base, [
                'first_name' => 'firstname',
                'last_name' => 'lastname',
                'phone' => 'mobilephone',
                'company' => 'company',
                'notes' => 'hs_lead_status',
            ]),
            'salesforce' => array_merge($base, [
                'first_name' => 'FirstName',
                'last_name' => 'LastName',
                'phone' => 'MobilePhone',
                'company' => 'Company',
                'lead_source' => 'LeadSource',
            ]),
            'zoho' => array_merge($base, [
                'first_name' => 'First_Name',
                'last_name' => 'Last_Name',
                'phone' => 'Mobile',
                'company' => 'Company',
            ]),
            'pipedrive' => array_merge($base, [
                'first_name' => 'name',
                'phone' => 'phone',
                'company' => 'org_name',
            ]),
            'freshsales' => array_merge($base, [
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'phone' => 'mobile_number',
            ]),
            'dynamics' => array_merge($base, [
                'first_name' => 'firstname',
                'last_name' => 'lastname',
                'phone' => 'mobilephone',
            ]),
            'gohighlevel' => array_merge($base, [
                'first_name' => 'firstName',
                'last_name' => 'lastName',
                'phone' => 'phone',
                'company' => 'companyName',
            ]),
            default => $base,
        };
    }
}
