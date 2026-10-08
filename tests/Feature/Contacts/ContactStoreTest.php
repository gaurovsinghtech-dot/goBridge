<?php

namespace Tests\Feature\Contacts;

use App\Events\ContactCreated;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ContactStoreTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->createWorkspaceContext();
    }

    public function test_adding_a_contact_dispatches_contact_created_once(): void
    {
        Event::fake([ContactCreated::class]);

        $this->actingAs($this->ctx['user'])
            ->post(route('client.contacts.store'), [
                'first_name' => 'Jane',
                'phone_e164' => '+14155550100',
            ])
            ->assertRedirect();

        Event::assertDispatchedTimes(ContactCreated::class, 1);
    }

    public function test_bulk_import_does_not_dispatch_contact_created(): void
    {
        Event::fake([ContactCreated::class]);

        $this->actingAs($this->ctx['user'])
            ->postJson(route('client.contacts.import-rows'), [
                'rows' => [['phone_e164' => '+14155550101', 'first_name' => 'Bulk']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('contacts', ['phone_e164' => '+14155550101']);
        Event::assertNotDispatched(ContactCreated::class);
    }

    public function test_adding_existing_phone_does_not_wipe_blank_fields(): void
    {
        Contact::create([
            'workspace_id' => $this->ctx['workspace']->id,
            'phone_e164' => '+14155550102',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
        ]);

        $this->actingAs($this->ctx['user'])
            ->post(route('client.contacts.store'), [
                'first_name' => 'Janet',
                'last_name' => '',
                'phone_e164' => '+14155550102',
                'email' => '',
            ])
            ->assertRedirect();

        $contact = Contact::where('phone_e164', '+14155550102')->first();
        $this->assertSame('Janet', $contact->first_name);
        $this->assertSame('Doe', $contact->last_name);
        $this->assertSame('jane@example.com', $contact->email);
    }

    public function test_adding_contact_requires_phone_or_email(): void
    {
        $this->actingAs($this->ctx['user'])
            ->post(route('client.contacts.store'), ['first_name' => 'Nobody'])
            ->assertSessionHasErrors('phone_e164');

        $this->assertDatabaseMissing('contacts', ['first_name' => 'Nobody']);
    }
}
