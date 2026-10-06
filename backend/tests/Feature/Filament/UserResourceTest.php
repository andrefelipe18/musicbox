<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Admin;
use App\Models\User;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Tests\Support\HeaderActionsProbePage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('resolves model and navigation labels from the active locale', function (): void {
    expect(UserResource::getModelLabel())->toBe('usuário')
        ->and(UserResource::getPluralModelLabel())->toBe('usuários')
        ->and(UserResource::getNavigationLabel())->toBe('Usuários')
        ->and(UserResource::getTitleCaseModelLabel())->toBe('Usuário');

    app()->setLocale('en');

    expect(UserResource::getModelLabel())->toBe('user')
        ->and(UserResource::getPluralModelLabel())->toBe('users')
        ->and(UserResource::getNavigationLabel())->toBe('Users');
});

it('groups the form into a single titled section', function (): void {
    livewire(CreateUser::class)
        ->assertSee(__('app.resources.users.sections.identity.heading'))
        ->assertDontSee(__('app.resources.users.sections.access.heading'));
});

it('requires a matching password confirmation when creating', function (): void {
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'different-password',
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'confirmed']);

    $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
});

it('hides the password confirmation when editing', function (): void {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->assertFormFieldHidden('password_confirmation');
});

it('labels every form field through the active locale', function (): void {
    $labels = [
        'name' => 'Nome',
        'email' => 'E-mail',
        'password' => 'Senha',
        'password_confirmation' => 'Confirmar senha',
    ];

    $page = livewire(CreateUser::class);

    foreach ($labels as $field => $label) {
        $page->assertFormFieldExists($field, fn ($component): bool => $component->getLabel() === $label);
    }
});

it('labels every table column through the active locale', function (): void {
    $labels = [
        'name' => 'Nome',
        'email' => 'E-mail',
        'email_verified_at' => 'E-mail verificado',
        'created_at' => 'Criado em',
        'updated_at' => 'Atualizado em',
    ];

    $page = livewire(ListUsers::class);

    foreach ($labels as $column => $label) {
        $page->assertTableColumnExists($column, fn ($component): bool => $component->getLabel() === $label);
    }
});

it('renders the verified badge from the active locale', function (): void {
    User::factory()->create(['name' => 'Verificado', 'email_verified_at' => now()]);
    User::factory()->create(['name' => 'Nao Verificado', 'email_verified_at' => null]);

    livewire(ListUsers::class)
        ->assertSee(__('app.resources.users.verified'))
        ->assertSee(__('app.resources.users.unverified'));
});

it('lists users with a phosphor navigation icon', function (): void {
    $users = User::factory()->count(3)->create();

    expect(UserResource::getNavigationIcon())->toBe(Phosphor::Users);

    livewire(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords($users)
        ->assertTableColumnExists('email_verified_at');
});

it('creates a user', function (): void {
    livewire(CreateUser::class)
        ->assertOk()
        ->fillForm([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
});

it('rejects a duplicate email', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Ada',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('edits a user without changing the password when left blank', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    $originalPassword = $user->password;

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh())
        ->name->toBe('New Name')
        ->password->toBe($originalPassword);
});

it('resets the password when a new one is provided', function (): void {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['password' => 'brand-new-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->password)->not->toBe('brand-new-password');
});

it('lets extending pages add header actions inside and outside the action group', function (): void {
    $page = livewire(HeaderActionsProbePage::class, ['record' => User::factory()->create()->getKey()]);

    $headerActions = $page->instance()->getCachedHeaderActions();

    expect($headerActions)->toHaveCount(3)
        ->and(array_keys($headerActions[0]->getFlatActions()))->toBe(['cancel', 'probeInsideGroup'])
        ->and($headerActions[1]->getName())->toBe('save')
        ->and($headerActions[2]->getName())->toBe('probeOutsideGroup');
});

it('drops the create another action from the create page', function (): void {
    livewire(CreateUser::class)
        ->assertActionDoesNotExist('createAnother');

    expect(livewire(CreateUser::class)->instance()->canCreateAnother())->toBeFalse();
});

it('renders the create page actions in the page header', function (): void {
    $page = livewire(CreateUser::class);

    $page
        ->assertActionExists('create')
        ->assertActionExists('cancel')
        ->assertOk();

    $headerActions = $page->instance()->getCachedHeaderActions();

    expect($page->instance()->getFormActions())->toBe([])
        ->and($headerActions[0])->toBeInstanceOf(ActionGroup::class)
        ->and($headerActions[0]->getTriggerView())->toBe(ActionGroup::BUTTON_VIEW)
        ->and(array_keys($headerActions[0]->getFlatActions()))->toBe(['cancel'])
        ->and($headerActions[1])->toBeInstanceOf(Action::class)
        ->and($headerActions[1]->getName())->toBe('create')
        ->and($headerActions[1]->getIcon())->toBe(Phosphor::Plus);
});

it('groups the cancel and delete actions before the save action', function (): void {
    $user = User::factory()->create();

    $page = livewire(EditUser::class, ['record' => $user->getKey()]);

    $page
        ->assertActionExists('save')
        ->assertActionExists('cancel')
        ->assertActionExists('delete')
        ->assertOk();

    $headerActions = $page->instance()->getCachedHeaderActions();

    expect($page->instance()->getFormActions())->toBe([])
        ->and($headerActions[0])->toBeInstanceOf(ActionGroup::class)
        ->and($headerActions[0]->getTriggerView())->toBe(ActionGroup::BUTTON_VIEW)
        ->and(array_keys($headerActions[0]->getFlatActions()))->toBe(['cancel', 'delete'])
        ->and($headerActions[1])->toBeInstanceOf(Action::class)
        ->and($headerActions[1]->getName())->toBe('save')
        ->and($headerActions[1]->getIcon())->toBe(Phosphor::FloppyDisk);
});

it('deletes a user', function (): void {
    $user = User::factory()->create();

    livewire(ListUsers::class)
        ->callTableAction(DeleteAction::class, $user);

    expect(User::find($user->getKey()))->toBeNull();
});

it('requires a password only when creating', function (): void {
    $user = User::factory()->create();

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Ada',
            'email' => 'ada@example.com',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'password' => 'required',
            'password_confirmation' => 'required',
        ]);

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->assertFormExists();
});
