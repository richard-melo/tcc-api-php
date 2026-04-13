<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

/**
 * Testes funcionais do CRUD de gastos.
 * Cobre todos os endpoints de /api/expenses com autenticação real via JWT.
 */
class ExpenseTest extends FunctionalTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->registerAndLogin();
        $this->withAuth($this->token);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'description'    => 'Almoço no centro',
            'amount'         => 42.50,
            'category'       => 'alimentacao',
            'payment_method' => 'pix',
            'expense_date'   => '2025-06-15',
        ], $overrides);
    }

    // ── POST /api/expenses ───────────────────────────────────────────────────

    public function testCreateExpenseReturnsCreatedExpense(): void
    {
        $response = $this->callExpense('store', $this->validPayload());

        $this->assertArrayHasKey('id', $response);
        $this->assertSame('Almoço no centro', $response['description']);
        $this->assertEqualsWithDelta(42.50, $response['amount'], 0.01);
        $this->assertSame('alimentacao', $response['category']);
    }

    public function testCreateExpenseFailsWithoutAuth(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $response = $this->callExpense('store', $this->validPayload());

        $this->assertArrayHasKey('error', $response);
    }

    public function testCreateExpenseFailsWithInvalidCategory(): void
    {
        $response = $this->callExpense('store', $this->validPayload(['category' => 'categoria-invalida']));

        $this->assertArrayHasKey('error', $response);
    }

    public function testCreateExpenseFailsWithNegativeAmount(): void
    {
        $response = $this->callExpense('store', $this->validPayload(['amount' => -10]));

        $this->assertArrayHasKey('error', $response);
    }

    public function testCreateExpenseFailsWithInvalidDateFormat(): void
    {
        $response = $this->callExpense('store', $this->validPayload(['expense_date' => '15/06/2025']));

        $this->assertArrayHasKey('error', $response);
    }

    public function testCreateExpenseFailsWithInvalidPaymentMethod(): void
    {
        $response = $this->callExpense('store', $this->validPayload(['payment_method' => 'boleto']));

        $this->assertArrayHasKey('error', $response);
    }

    // ── GET /api/expenses ────────────────────────────────────────────────────

    public function testListExpensesReturnsArray(): void
    {
        $this->callExpense('store', $this->validPayload(['description' => 'Gasto A']));
        $this->callExpense('store', $this->validPayload(['description' => 'Gasto B']));

        $response = $this->callExpense('index');

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
    }

    public function testListExpensesFiltersByCategory(): void
    {
        $this->callExpense('store', $this->validPayload(['category' => 'alimentacao']));
        $this->callExpense('store', $this->validPayload(['category' => 'transporte']));

        $response = $this->callExpense('index', query: ['category' => 'transporte']);

        $this->assertCount(1, $response);
        $this->assertSame('transporte', $response[0]['category']);
    }

    public function testListExpensesFiltersByDateRange(): void
    {
        $this->callExpense('store', $this->validPayload(['expense_date' => '2025-05-01']));
        $this->callExpense('store', $this->validPayload(['expense_date' => '2025-06-15']));
        $this->callExpense('store', $this->validPayload(['expense_date' => '2025-07-01']));

        $response = $this->callExpense('index', query: [
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);

        $this->assertCount(1, $response);
        $this->assertSame('2025-06-15', $response[0]['expense_date']);
    }

    // ── GET /api/expenses/{id} ───────────────────────────────────────────────

    public function testShowReturnsExpense(): void
    {
        $created  = $this->callExpense('store', $this->validPayload());
        $response = $this->callExpense('show', id: $created['id']);

        $this->assertSame($created['id'], $response['id']);
        $this->assertSame('Almoço no centro', $response['description']);
    }

    public function testShowReturnsErrorForNonExistentId(): void
    {
        $response = $this->callExpense('show', id: 9999);

        $this->assertArrayHasKey('error', $response);
    }

    // ── PUT /api/expenses/{id} ───────────────────────────────────────────────

    public function testUpdateExpense(): void
    {
        $created  = $this->callExpense('store', $this->validPayload());
        $response = $this->callExpense('update', body: ['description' => 'Jantar atualizado', 'amount' => 75.00], id: $created['id']);

        $this->assertSame('Jantar atualizado', $response['description']);
        $this->assertEqualsWithDelta(75.00, $response['amount'], 0.01);
        $this->assertSame('alimentacao', $response['category']); // não mudou
    }

    public function testUpdateReturnsErrorForNonExistentId(): void
    {
        $response = $this->callExpense('update', body: ['description' => 'Tentativa'], id: 9999);

        $this->assertArrayHasKey('error', $response);
    }

    // ── DELETE /api/expenses/{id} ────────────────────────────────────────────

    public function testDeleteExpense(): void
    {
        $created  = $this->callExpense('store', $this->validPayload());
        $response = $this->callExpense('destroy', id: $created['id']);

        $this->assertArrayHasKey('message', $response);

        // Confirma que sumiu
        $notFound = $this->callExpense('show', id: $created['id']);
        $this->assertArrayHasKey('error', $notFound);
    }

    public function testDeleteReturnsErrorForNonExistentId(): void
    {
        $response = $this->callExpense('destroy', id: 9999);

        $this->assertArrayHasKey('error', $response);
    }

    // ── Isolamento entre usuários ────────────────────────────────────────────

    public function testUserCannotSeeAnotherUsersExpenses(): void
    {
        // Cria gasto do usuário 1
        $created = $this->callExpense('store', $this->validPayload());

        // Autentica como usuário 2
        $token2 = $this->registerAndLogin('Outro', 'outro@teste.com', 'senha456');
        $this->withAuth($token2);

        $response = $this->callExpense('show', id: $created['id']);

        $this->assertArrayHasKey('error', $response);
    }
}
