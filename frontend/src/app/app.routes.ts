import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';

// standalone-компоненты загружаются лениво (отдельные chunk'и)
export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login.component').then((m) => m.LoginComponent),
  },
  {
    path: '',
    canActivate: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'products' },
      {
        path: 'import',
        loadComponent: () => import('./pages/import/import.component').then((m) => m.ImportComponent),
      },
      {
        path: 'products',
        loadComponent: () =>
          import('./pages/products-list/products-list.component').then((m) => m.ProductsListComponent),
      },
      {
        path: 'products/:id',
        loadComponent: () =>
          import('./pages/product-detail/product-detail.component').then((m) => m.ProductDetailComponent),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
