<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Filament\Forms\PublicImageUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_name')
                    ->label('Nama tampilan')->required()->maxLength(255),
                TextInput::make('customer_role')
                    ->label('Peran tampilan')->maxLength(255),
                Textarea::make('content')
                    ->label('Testimoni')->required()->maxLength(3000)
                    ->columnSpanFull(),
                TextInput::make('rating')
                    ->label('Rating')->required()->numeric()->integer()->minValue(1)->maxValue(5),
                PublicImageUpload::make('photo', 'Foto pelanggan', 'testimonials/photos')
                    ->imagePreviewHeight('160')
                    ->itemPanelAspectRatio('1:1')
                    ->helperText('Gunakan foto persegi. Format JPG, PNG, atau WebP, maksimal 4 MB.'),
                Toggle::make('is_published')
                    ->label('Terbit')->default(false),
                Toggle::make('is_demo')
                    ->label('Data demo')->default(true)->required(),
            ]);
    }
}
