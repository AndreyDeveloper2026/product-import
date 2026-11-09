import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { Paginated, Product, ProductQuery, ProductSummary } from '../models/product';

@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly http = inject(HttpClient);

  list(query: ProductQuery): Observable<Paginated<ProductSummary>> {
    let params = new HttpParams().set('page', query.page).set('limit', query.limit);
    if (query.name.trim() !== '') {
      params = params.set('name', query.name.trim());
    }
    if (query.priceMin !== null) {
      params = params.set('priceMin', query.priceMin);
    }
    if (query.priceMax !== null) {
      params = params.set('priceMax', query.priceMax);
    }
    return this.http.get<Paginated<ProductSummary>>('/api/products', { params });
  }

  get(id: number): Observable<Product> {
    return this.http.get<Product>(`/api/products/${id}`);
  }
}
