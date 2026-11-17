<?php

namespace App\Filament\Resources\Dues\Pages;

use App\Filament\Resources\Dues\DueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDues extends ListRecords
{
    protected static string $resource = DueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Iuran')
                ->icon('heroicon-o-plus')
                ->visible(fn(): bool => DueResource::canCreate()),
        ];
    }
}
