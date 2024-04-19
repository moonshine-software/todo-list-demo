<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\TodoItem;
use Illuminate\Support\Collection;
use Illuminate\View\ComponentAttributeBag;
use MoonShine\ActionButtons\ActionButton;
use MoonShine\Components\FormBuilder;
use MoonShine\Components\Icon;
use MoonShine\Components\TableBuilder;
use MoonShine\Enums\JsEvent;
use MoonShine\Enums\ToastType;
use MoonShine\Fields\DateRange;
use MoonShine\Fields\ID;
use MoonShine\Fields\Position;
use MoonShine\Fields\Preview;
use MoonShine\Fields\StackFields;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Http\Responses\MoonShineJsonResponse;
use MoonShine\MoonShineRequest;
use MoonShine\Pages\Page;
use MoonShine\Support\AlpineJs;
use MoonShine\TypeCasts\ModelCast;
use Throwable;

class Dashboard extends Page
{
    public function breadcrumbs(): array
    {
        return [
            '#' => $this->title(),
        ];
    }

    public function title(): string
    {
        return 'My TODO-list';
    }

    public function components(): array
    {
        return [
            ActionButton::make('New task')
                ->primary()
                ->icon('heroicons.outline.plus')
                ->inModal('New task', fn () => $this->formComponent())
            ,

            TableBuilder::make(fields: $this->listFields())
                ->customAttributes([
                    'data-handle' => '.handle',
                ])
                ->tdAttributes(
                    fn (mixed $data, int $row, int $cell, ComponentAttributeBag $attr) => $attr->when(
                        $cell === 0,
                        fn (ComponentAttributeBag $a) => $a->merge(['class' => 'handle', 'style' => 'cursor: move'])
                    )
                )
                ->name('todo-list')
                ->items($this->items())
                ->cast(ModelCast::make(TodoItem::class))
                ->async()
                ->sortable($this->asyncMethodUrl('reorder'))
                ->reindex()
                ->withNotFound()
                ->buttons([
                    ActionButton::make('')
                        ->secondary()
                        ->icon('heroicons.outline.pencil')
                        ->inModal('Update task', fn (TodoItem $todoItem) => $this->formComponent($todoItem))
                    ,

                    ActionButton::make('')
                        ->icon('heroicons.outline.check')
                        ->success()
                        ->method('done', events: $this->updateListingEvents()),
                ])
            ,
        ];
    }

    private function items(): Collection
    {
        return TodoItem::query()
            ->orderBy('sort_order')
            ->get();
    }

    private function listFields(): array
    {
        return [
            Preview::make(
                formatted: static fn () => Icon::make('heroicons.outline.bars-4')
            ),

            Position::make(),

            StackFields::make()->fields([
                Text::make('Title')->badge(),
                Text::make('Description'),
                DateRange::make('Date')
                    ->nullable()
                    ->withTime()
                    ->fromTo('from', 'to')
                    ->format('d.m.Y H:i'),
            ]),
        ];
    }

    private function updateListingEvents(): array
    {
        return [
            AlpineJs::event(JsEvent::TABLE_UPDATED, 'todo-list'),
            AlpineJs::event(JsEvent::FORM_RESET, 'todo-list-form'),
        ];
    }

    /**
     * @throws Throwable
     */
    private function formComponent(?TodoItem $todoItem = null): FormBuilder
    {
        return FormBuilder::make()
            ->name('todo-list-form')
            ->asyncMethod(
                'save',
                events: $this->updateListingEvents()
            )
            ->fields($this->formFields())
            ->fillCast($todoItem, ModelCast::make(TodoItem::class))
            ->submit('Save', ['class' => 'btn-primary btn-lg']);
    }

    private function formFields(): array
    {
        return [
            ID::make(),
            Text::make('Title'),
            Textarea::make('Description'),
            DateRange::make('Date')
                ->nullable()
                ->fromTo('from', 'to'),
        ];
    }

    public function reorder(MoonShineRequest $request): MoonShineJsonResponse
    {
        $request->string('data')->explode(',')->each(
            fn (string $id, int $sortOrder) => TodoItem::query()
                ->find((int) $id)
                ?->update(['sort_order' => $sortOrder])
        );

        return MoonShineJsonResponse::make();
    }

    public function done(MoonShineRequest $request): MoonShineJsonResponse
    {
        TodoItem::query()
            ->find($request->getItemID())
            ?->delete();

        return MoonShineJsonResponse::make()
            ->toast('Congratulation', ToastType::SUCCESS);
    }

    public function save(MoonShineRequest $request): MoonShineJsonResponse
    {
        $request->validate([
            'title' => ['required', 'string'],
        ]);

        TodoItem::query()->updateOrCreate(['id' => $request->integer('id')], [
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'from' => data_get($request->get('date'), 'from'),
            'to' => data_get($request->get('date'), 'to'),
        ]);

        return MoonShineJsonResponse::make()
            ->toast('Added', ToastType::SUCCESS);
    }
}
