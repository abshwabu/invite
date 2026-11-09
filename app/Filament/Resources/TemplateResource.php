<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TemplateResource\Pages;
use App\Models\Category;
use App\Models\Template;
use App\Services\DesignValidator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'Catalog & Design';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name_en')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('kind')
                            ->options([
                                'invitation' => 'Invitation Card',
                                'thank_you' => 'Thank-You Card',
                            ])
                            ->default('invitation')
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('preview_path')
                            ->label('Preview Path / URL')
                            ->required()
                            ->maxLength(255)
                            ->helperText('e.g. /storage/templates/wedding-royal.png'),
                        Forms\Components\TextInput::make('width')
                            ->numeric()
                            ->default(1080)
                            ->required(),
                        Forms\Components\TextInput::make('height')
                            ->numeric()
                            ->default(1528)
                            ->required(),
                        Forms\Components\TextInput::make('sort')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_system')
                            ->label('System Official Template')
                            ->default(true),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active in Gallery')
                            ->default(true),
                    ])->columns(3),

                Forms\Components\Section::make('Design JSON Specification')
                    ->description('Konva canvas contract format: version, background, layers array')
                    ->schema([
                        Forms\Components\Textarea::make('design_json')
                            ->label('Design JSON')
                            ->rows(18)
                            ->columnSpanFull()
                            ->required()
                            ->formatStateUsing(function ($state) {
                                if (is_array($state)) {
                                    return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                }

                                return $state;
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if (is_string($state)) {
                                    return json_decode($state, true);
                                }

                                return $state;
                            })
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, $fail) {
                                        try {
                                            DesignValidator::validate($value);
                                        } catch (ValidationException $e) {
                                            $messages = implode(' ', $e->validator->errors()->all());
                                            $fail("Invalid Design JSON: {$messages}");
                                        } catch (\Throwable $e) {
                                            $fail("Invalid Design JSON: {$e->getMessage()}");
                                        }
                                    };
                                },
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_path')
                    ->label('Preview')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name_en')
                    ->label('Category')
                    ->badge(),
                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'thank_you' => 'success',
                        default => 'info',
                    }),
                Tables\Columns\IconColumn::make('is_system')
                    ->label('System')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort')
                    ->sortable(),
            ])
            ->defaultSort('sort', 'asc')
            ->reorderable('sort')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name_en'),
                Tables\Filters\SelectFilter::make('kind')
                    ->options([
                        'invitation' => 'Invitation',
                        'thank_you' => 'Thank-You',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TernaryFilter::make('is_system'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTemplates::route('/'),
            'create' => Pages\CreateTemplate::route('/create'),
            'edit' => Pages\EditTemplate::route('/{record}/edit'),
        ];
    }
}
