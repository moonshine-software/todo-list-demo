<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\TodoItem;
use Illuminate\Support\Collection;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Laravel\TypeCasts\ModelCaster;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Icon;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\Fieldset;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Position;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Throwable;

class Dashboard extends Page
{
    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle()
        ];
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Dashboard';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
	{
		return [
            ActionButton::make('New task')
                ->primary()
                ->icon('plus')
                ->inModal('New task', fn () => $this->formComponent())
            ,

            TableBuilder::make(fields: $this->listFields())
                ->customAttributes([
                    'data-handle' => '.handle',
                ])
                ->tdAttributes(
                    fn (?DataWrapperContract $data, int $row, int $cell, TableBuilder $ctx): array =>
                    $cell === 0 ? ['class' => 'handle', 'style' => 'cursor: move'] : []
                )
                ->name('todo-list')
                ->items($this->items())
                ->cast(new ModelCaster(TodoItem::class))
                ->async()
                ->reorderable($this->getRouter()->getEndpoints()->method('reorder'))
                ->reindex()
                ->withNotFound()
                ->buttons([
                    ActionButton::make('')
                        ->secondary()
                        ->icon('pencil')
                        ->inModal(
                            title: 'Update task',
                            content: fn (TodoItem $todoItem) => $this->formComponent($todoItem),
                            name: fn(TodoItem $todoItem) => "task-edit-{$todoItem->getKey()}"
                        )
                    ,

                    ActionButton::make('')
                        ->icon('check')
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
                formatted: static fn () => Icon::make('bars-4')
            ),

            Position::make(),

            Fieldset::make()->fields([
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
            ->fillCast($todoItem, new ModelCaster(TodoItem::class))
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

    #[AsyncMethod]
    public function reorder(CrudRequestContract $request): JsonResponse
    {
        $request->string('data')->explode(',')->each(
            fn (string $id, int $sortOrder) => TodoItem::query()
                ->find((int) $id)
                ?->update(['sort_order' => $sortOrder])
        );

        return JsonResponse::make();
    }

    #[AsyncMethod]
    public function done(CrudRequestContract $request): JsonResponse
    {
        TodoItem::query()
            ->find($request->getItemID())
            ?->delete();

        return JsonResponse::make()
            ->toast('Congratulation', ToastType::SUCCESS);
    }

    #[AsyncMethod]
    public function save(CrudRequestContract $request): JsonResponse
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

        return JsonResponse::make()
            ->toast('Added', ToastType::SUCCESS);
    }
}
