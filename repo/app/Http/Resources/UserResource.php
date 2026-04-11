<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'email' => $this->maskField($this->email),
            'phone' => $this->maskField($this->phone),
            'account_status' => $this->account_status,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'membership' => $this->whenLoaded('membership'),
            'force_password_change' => $this->force_password_change,
            'created_at' => $this->created_at,
        ];
    }

    protected function maskField(?string $value): ?string
    {
        if (!$value) return null;
        if (strlen($value) <= 4) return '****';
        return substr($value, 0, 2) . str_repeat('*', strlen($value) - 4) . substr($value, -2);
    }
}
