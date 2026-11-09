import { createReducer, on } from '@ngrx/store';
import { ProductQuery, ProductSummary } from '../../models/product';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

export interface ProductsState {
  items: ProductSummary[];
  loading: boolean;
  error: string | null;
  query: ProductQuery;
  total: number;
  totalPages: number;
}

export const initialQuery: ProductQuery = { page: 1, limit: 20, name: '', priceMin: null, priceMax: null };

export const initialState: ProductsState = {
  items: [],
  loading: false,
  error: null,
  query: initialQuery,
  total: 0,
  totalPages: 1,
};

export const productsReducer = createReducer(
  initialState,
  on(loadProducts, (state, { query }) => ({ ...state, loading: true, error: null, query })),
  on(loadProductsSuccess, (state, { response }) => ({
    ...state,
    loading: false,
    items: response.items,
    total: response.total,
    totalPages: response.totalPages,
  })),
  on(loadProductsFailure, (state, { error }) => ({ ...state, loading: false, error })),
);
