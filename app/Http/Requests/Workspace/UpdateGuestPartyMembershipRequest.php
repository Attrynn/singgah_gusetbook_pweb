<?php

namespace App\Http\Requests\Workspace;

use App\Models\GuestPartyMembership;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestPartyMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        if (! $member instanceof GuestPartyMembership) {
            return false;
        }

        return $this->user()?->can('update', $member) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:active,inactive,removed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama individu anggota party wajib diisi.',
        ];
    }
}
