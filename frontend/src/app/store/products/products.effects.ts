import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, map, of, switchMap } from 'rxjs';
import { ProductService } from '../../services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

@Injectable()
export class ProductsEffects {
  private readonly actions$ = inject(Actions);
  private readonly productService = inject(ProductService);

  readonly load$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      // switchMap отменяет устаревший запрос при быстром вводе в фильтр / смене страницы
      switchMap(({ query }) =>
        this.productService.list(query).pipe(
          map((response) => loadProductsSuccess({ response })),
          catchError((err: HttpErrorResponse) =>
            of(loadProductsFailure({ error: err.error?.error ?? 'Не удалось загрузить товары' })),
          ),
        ),
      ),
    ),
  );
}
