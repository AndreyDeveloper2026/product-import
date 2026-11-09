import { createFeatureSelector, createSelector } from '@ngrx/store';
import { ProductsState } from './products.reducer';

export const selectProductsState = createFeatureSelector<ProductsState>('products');

export const selectAllProducts = createSelector(selectProductsState, (s) => s.items);
export const selectProductsLoading = createSelector(selectProductsState, (s) => s.loading);
export const selectProductsError = createSelector(selectProductsState, (s) => s.error);
export const selectTotalPages = createSelector(selectProductsState, (s) => s.totalPages);
export const selectTotalProducts = createSelector(selectProductsState, (s) => s.total);
export const selectProductsQuery = createSelector(selectProductsState, (s) => s.query);
