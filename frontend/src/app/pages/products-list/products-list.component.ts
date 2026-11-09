import { AsyncPipe, DecimalPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, DestroyRef, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { debounceTime } from 'rxjs';
import { ProductQuery } from '../../models/product';
import { loadProducts } from '../../store/products/products.actions';
import {
  selectAllProducts,
  selectProductsError,
  selectProductsLoading,
  selectProductsQuery,
  selectTotalPages,
  selectTotalProducts,
} from '../../store/products/products.selectors';

@Component({
  selector: 'app-products-list',
  standalone: true,
  imports: [
    AsyncPipe,
    DecimalPipe,
    ReactiveFormsModule,
    RouterLink,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatPaginatorModule,
    MatProgressBarModule,
    MatTableModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="page">
      <mat-card>
        <mat-card-content>
          <form [formGroup]="filter" class="filters">
            <mat-form-field class="grow">
              <mat-label>Поиск по наименованию</mat-label>
              <input matInput formControlName="name" data-testid="filter-name" />
            </mat-form-field>
            <mat-form-field>
              <mat-label>Цена от</mat-label>
              <input matInput type="number" min="0" formControlName="priceMin" />
            </mat-form-field>
            <mat-form-field>
              <mat-label>Цена до</mat-label>
              <input matInput type="number" min="0" formControlName="priceMax" />
            </mat-form-field>
          </form>

          @if (loading$ | async) {
            <mat-progress-bar mode="indeterminate" />
          }
          @if (error$ | async; as error) {
            <p class="err">{{ error }}</p>
          }

          <table mat-table [dataSource]="(products$ | async) ?? []" class="full" data-testid="products-table">
            <ng-container matColumnDef="thumb">
              <th mat-header-cell *matHeaderCellDef></th>
              <td mat-cell *matCellDef="let p">
                @if (p.thumbnailUrl) {
                  <img [src]="p.thumbnailUrl" alt="" class="thumb" />
                }
              </td>
            </ng-container>
            <ng-container matColumnDef="name">
              <th mat-header-cell *matHeaderCellDef>Наименование</th>
              <td mat-cell *matCellDef="let p">
                <a [routerLink]="['/products', p.id]" class="link">{{ p.name }}</a>
              </td>
            </ng-container>
            <ng-container matColumnDef="externalCode">
              <th mat-header-cell *matHeaderCellDef>Код</th>
              <td mat-cell *matCellDef="let p">{{ p.externalCode }}</td>
            </ng-container>
            <ng-container matColumnDef="price">
              <th mat-header-cell *matHeaderCellDef>Цена</th>
              <td mat-cell *matCellDef="let p">{{ p.price | number: '1.2-2' }}</td>
            </ng-container>
            <ng-container matColumnDef="discount">
              <th mat-header-cell *matHeaderCellDef>Скидка, %</th>
              <td mat-cell *matCellDef="let p">{{ p.discount | number: '1.0-2' }}</td>
            </ng-container>

            <tr mat-header-row *matHeaderRowDef="columns"></tr>
            <tr mat-row *matRowDef="let row; columns: columns"></tr>
            <tr class="mat-row" *matNoDataRow>
              <td class="mat-cell empty" [attr.colspan]="columns.length">Нет товаров</td>
            </tr>
          </table>

          @if (query$ | async; as query) {
            <mat-paginator
              [length]="(total$ | async) ?? 0"
              [pageIndex]="query.page - 1"
              [pageSize]="query.limit"
              [pageSizeOptions]="[10, 20, 50, 100]"
              (page)="onPage($event)"
              showFirstLastButtons
            />
            <p class="muted pages">Страниц: {{ totalPages$ | async }}</p>
          }
        </mat-card-content>
      </mat-card>
    </div>
  `,
  styles: [
    `
      .filters { display: flex; gap: 16px; flex-wrap: wrap; }
      .grow { flex: 1 1 280px; }
      .full { width: 100%; }
      .thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; }
      .link { color: #1565c0; text-decoration: none; font-weight: 500; }
      .link:hover { text-decoration: underline; }
      .empty { padding: 24px; text-align: center; color: rgba(0, 0, 0, 0.6); }
      .err { color: #b00020; }
      .pages { margin: 0 16px 8px; }
    `,
  ],
})
export class ProductsListComponent implements OnInit {
  private readonly store = inject(Store);
  private readonly fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly columns = ['thumb', 'name', 'externalCode', 'price', 'discount'];

  protected readonly products$ = this.store.select(selectAllProducts);
  protected readonly loading$ = this.store.select(selectProductsLoading);
  protected readonly error$ = this.store.select(selectProductsError);
  protected readonly totalPages$ = this.store.select(selectTotalPages);
  protected readonly total$ = this.store.select(selectTotalProducts);
  protected readonly query$ = this.store.select(selectProductsQuery);

  protected readonly filter = this.fb.group({
    name: this.fb.nonNullable.control(''),
    priceMin: this.fb.control<number | null>(null),
    priceMax: this.fb.control<number | null>(null),
  });

  private limit = 20;

  ngOnInit(): void {
    this.load(1);

    // поиск/фильтры: debounce, затем серверный запрос с первой страницы
    this.filter.valueChanges
      .pipe(debounceTime(300), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.load(1));
  }

  protected onPage(event: PageEvent): void {
    this.limit = event.pageSize;
    this.load(event.pageIndex + 1);
  }

  private load(page: number): void {
    const { name, priceMin, priceMax } = this.filter.getRawValue();
    const query: ProductQuery = {
      page,
      limit: this.limit,
      name,
      priceMin: priceMin === null || priceMin === undefined ? null : Number(priceMin),
      priceMax: priceMax === null || priceMax === undefined ? null : Number(priceMax),
    };
    this.store.dispatch(loadProducts({ query }));
  }
}
