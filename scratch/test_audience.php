<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contactsWith91 = \App\Modules\Shared\Models\Contact::where('phone_e164', 'LIKE', '%91%')->get();
echo "Contacts with +91: " . $contactsWith91->count() . "\n";
foreach ($contactsWith91 as $c) {
    echo "ID={$c->id}: {$c->first_name} {$c->last_name} | Phone: '{$c->phone_e164}' | Email: '{$c->email}' | WA Opt-In: " . var_export($c->opt_in_whatsapp, true) . " | Workspace={$c->workspace_id}\n";
}

$recentContacts = \App\Modules\Shared\Models\Contact::orderByDesc('id')->take(10)->get();
echo "\nTop 10 Recent Contacts:\n";
foreach ($recentContacts as $c) {
    echo "ID={$c->id}: {$c->first_name} {$c->last_name} | Phone: '{$c->phone_e164}' | Email: '{$c->email}' | WA Opt-In: " . var_export($c->opt_in_whatsapp, true) . " | Workspace={$c->workspace_id}\n";
}
