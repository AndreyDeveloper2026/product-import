import { createAction, props } from '@ngrx/store';
import { Paginated, ProductQuery, ProductSummary } from '../../models/product';

export const loadProducts = createAction('[Products] Load', props<{ query: ProductQuery }>());
export const loadProductsSuccess = createAction(
  '[Products] Load Success',
  props<{ response: Paginated<ProductSummary> }>(),
);
export const loadProductsFailure = createAction('[Products] Load Failure', props<{ error: string }>());
