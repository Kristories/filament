<?php

namespace Filament\Http\Livewire;

use Livewire\{
    Component,
    WithFileUploads,
};
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{
    Auth,
    Hash,
    Storage,
};
use Filament\Traits\WithNotifications;
use Filament\Fields\{
    Tabs,
    Layout,
    Text,
    Avatar,
    Fieldset,
};

class Profile extends Component
{
    use WithFileUploads, WithNotifications;
    
    public $user;
    public $avatar;
    public $password;
    public $password_confirmation;

    protected $rules = [
        'user.name' => 'required|string|min:2|max:255',
        'user.email' => 'required|string|email|max:255',
        'avatar' => 'nullable|image|max:1024',
        'password' => 'nullable|string|required_with:password_confirmation|min:6|confirmed',
        'password_confirmation' => 'nullable|string|same:password',
    ];

    public function mount(): void
    {
        $this->user = Auth::user();
    }

    public function updatedAvatar($value): void
    {
        $this->validate([
            'avatar' => $this->rules['avatar'],
        ]);
    }

    public function updatedUserEmail($value): void
    {
        $this->validate([
            'user.email' => [
                Rule::unique('users', 'email')->ignore($this->user->id),
            ],
        ]);
    }

    public function deleteAvatar(): void
    {
        $avatar = $this->user->avatar;
        
        if ($avatar) {
            Storage::disk(config('filament.storage_disk'))->delete($avatar);
            $this->avatar = null;
            
            $this->user->avatar = null;
            $this->user->save();

            $this->notify(__('Avatar removed for :name', ['name' => $this->user->name]));
        }
    }

    /**
     * @return array
     *
     * @psalm-return array{0: mixed}
     */
    public function fields(): array
    {
        return [
            Tabs::label('Profile')
                ->tab('Account', [
                    Layout::make('grid grid-cols-1 lg:grid-cols-2 gap-6')->fields([
                        Text::make('user.name')
                            ->label('Name')
                            ->extraAttributes([
                                'required' => 'true',
                            ]),
                        Text::make('user.email')
                            ->type('email')
                            ->label('E-Mail Address')
                            ->modelDirective('wire:model.lazy')
                            ->extraAttributes([
                                'required' => 'true',
                                'autocomplete' => 'email',
                            ]),                      
                    ]),   
                    Avatar::make('avatar')
                        ->label('User Photo')
                        ->avatar($this->avatar)
                        ->user($this->user)
                        ->deleteMethod('deleteAvatar'),
                    Fieldset::make('Update Password')
                        ->fields([
                            Text::make('password')
                                ->type('password')
                                ->label('Password')
                                ->extraAttributes([
                                    'autocomplete' => 'new-password',
                                ])
                                ->hint(__('Optional'))
                                ->help('Leave blank to keep current password.'),
                            Text::make('password_confirmation')
                                ->type('password')
                                ->label('Confirm New Password')
                                ->extraAttributes([
                                    'autocomplete' => 'new-password',
                                ])
                                ->hint(__('Optional')),
                        ])
                        ->class('grid grid-cols-1 lg:grid-cols-2 gap-6'),
                ]),
        ];
    }

    public function submit(): void
    {
        $this->validate();

        if ($this->avatar) {
            $this->user->avatar = $this->avatar->store('avatars', config('filament.storage_disk'));
        }
        
        if ($this->password) {
            $this->user->password = Hash::make($this->password);
        }

        $this->user->save();

        $this->reset(['password', 'password_confirmation']);

        $this->notify(__('Profile saved!'));
    }

    public function render(): \Illuminate\View\View
    {
        return view('filament::livewire.profile')
            ->layout('filament::layouts.app', ['title' => __('Profile')]);;
    }
}