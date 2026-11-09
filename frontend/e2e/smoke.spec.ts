import { expect, test } from '@playwright/test';

// UI e2e: бэкенд мокается через page.route, поэтому для запуска нужен только `ng serve`.
const items = Array.from({ length: 3 }, (_, i) => ({
  id: i + 1,
  externalCode: `E-${i + 1}`,
  name: `Товар ${i + 1}`,
  price: 100 * (i + 1),
  discount: 10,
  thumbnailUrl: null,
}));

test('login → список товаров → карточка', async ({ page }) => {
  await page.route('**/api/auth/login', (route) =>
    route.fulfill({ json: { token: 'fake.jwt.token', expiresIn: 3600 } }),
  );
  await page.route('**/api/products?**', (route) =>
    route.fulfill({ json: { items, page: 1, limit: 20, total: 3, totalPages: 1 } }),
  );
  await page.route('**/api/products/2', (route) =>
    route.fulfill({
      json: {
        id: 2,
        externalCode: 'E-2',
        name: 'Товар 2',
        description: 'Описание',
        price: 200,
        discount: 10,
        attributes: [{ key: 'Цвет', value: 'Красный' }],
        images: [],
      },
    }),
  );
  await page.route('**/api/imports/latest', (route) => route.fulfill({ status: 404, json: { error: 'none' } }));

  // защищённый маршрут без токена → редирект на /login
  await page.goto('/products');
  await expect(page).toHaveURL(/\/login/);

  await page.getByTestId('username').fill('admin');
  await page.getByTestId('password').fill('admin123');
  await page.getByRole('button', { name: 'Войти' }).click();

  await expect(page).toHaveURL(/\/products$/);
  await expect(page.getByTestId('products-table').getByRole('link')).toHaveCount(3);

  await page.getByRole('link', { name: 'Товар 2' }).click();
  await expect(page.getByTestId('product-name')).toHaveText('Товар 2');
  await expect(page.getByText('Красный')).toBeVisible();
});
