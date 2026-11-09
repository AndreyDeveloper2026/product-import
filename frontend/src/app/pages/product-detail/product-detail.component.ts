import { AsyncPipe, DecimalPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, map, of, switchMap } from 'rxjs';
import { ImportJob, isActive } from '../../models/import-job';
import { ImportService } from '../../services/import.service';
import { ProductService } from '../../services/product.service';
import { ImportStatusComponent } from '../../shared/import-status.component';

@Component({
  selector: 'app-product-detail',
  standalone: true,
  imports: [
    AsyncPipe,
    DecimalPipe,
    RouterLink,
    MatButtonModule,
    MatCardModule,
    MatIconModule,
    MatProgressSpinnerModule,
    ImportStatusComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="page">
      <a mat-button routerLink="/products"><mat-icon>arrow_back</mat-icon> К списку</a>

      @if (latestImport$ | async; as job) {
        <mat-card class="block">
          <mat-card-header><mat-card-subtitle>Последний импорт</mat-card-subtitle></mat-card-header>
          <mat-card-content><app-import-status [job]="job" /></mat-card-content>
        </mat-card>
      }

      @if (product$ | async; as state) {
        @if (state.product; as p) {
          <mat-card class="block">
            <mat-card-header>
              <mat-card-title data-testid="product-name">{{ p.name }}</mat-card-title>
              <mat-card-subtitle>Код: {{ p.externalCode }}</mat-card-subtitle>
            </mat-card-header>
            <mat-card-content>
              <div class="price">
                <span class="amount">{{ p.price | number: '1.2-2' }}</span>
                @if (p.discount !== 0) {
                  <span class="chip">Скидка {{ p.discount | number: '1.0-2' }}%</span>
                }
              </div>
              @if (p.description) {
                <p>{{ p.description }}</p>
              }
            </mat-card-content>
          </mat-card>

          <mat-card class="block">
            <mat-card-header><mat-card-title>Дополнительные атрибуты</mat-card-title></mat-card-header>
            <mat-card-content>
              @if (p.attributes.length === 0) {
                <p class="muted">Нет атрибутов</p>
              } @else {
                <table class="attrs">
                  @for (a of p.attributes; track $index) {
                    <tr>
                      <th>{{ a.key }}</th>
                      <td>{{ a.value }}</td>
                    </tr>
                  }
                </table>
              }
            </mat-card-content>
          </mat-card>

          <mat-card class="block">
            <mat-card-header><mat-card-title>Изображения</mat-card-title></mat-card-header>
            <mat-card-content>
              @if (p.images.length === 0) {
                <p class="muted">Нет изображений</p>
              } @else {
                <div class="gallery">
                  @for (img of p.images; track img.url) {
                    <a [href]="img.publicUrl" target="_blank" rel="noopener">
                      <img [src]="img.publicUrl" [alt]="p.name" />
                    </a>
                  }
                </div>
              }
            </mat-card-content>
          </mat-card>
        } @else {
          <p class="err">{{ state.error }}</p>
        }
      } @else {
        <mat-spinner diameter="40" />
      }
    </div>
  `,
  styles: [
    `
      .block { margin-top: 16px; }
      .price { display: flex; align-items: center; gap: 12px; margin: 8px 0; }
      .amount { font-size: 28px; font-weight: 500; }
      .chip { background: #e8f0fe; color: #1a56c4; border-radius: 16px; padding: 4px 12px; font-size: 14px; }
      .attrs { border-collapse: collapse; }
      .attrs th { text-align: left; padding: 6px 24px 6px 0; color: rgba(0, 0, 0, 0.6); font-weight: 500; }
      .gallery { display: flex; gap: 12px; flex-wrap: wrap; }
      .gallery img { height: 180px; border-radius: 6px; object-fit: cover; }
      .err { color: #b00020; }
    `,
  ],
})
export class ProductDetailComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly products = inject(ProductService);
  private readonly importService = inject(ImportService);

  protected readonly product$ = this.route.paramMap.pipe(
    map((params) => Number(params.get('id'))),
    switchMap((id) =>
      this.products.get(id).pipe(
        map((product) => ({ product, error: null as string | null })),
        catchError(() => of({ product: null, error: 'Товар не найден' })),
      ),
    ),
  );

  /** Последний импорт; если он ещё идёт — polling до завершения. */
  protected readonly latestImport$ = this.importService.latest().pipe(
    switchMap((job: ImportJob) => (isActive(job) ? this.importService.watch(job.id) : of(job))),
    catchError(() => of(null)),
  );
}
