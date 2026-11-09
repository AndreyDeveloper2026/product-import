import { AsyncPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { Observable, Subject, catchError, of, switchMap, tap } from 'rxjs';
import { ImportJob, isActive } from '../../models/import-job';
import { ImportService } from '../../services/import.service';
import { ImportStatusComponent } from '../../shared/import-status.component';

@Component({
  selector: 'app-import',
  standalone: true,
  imports: [AsyncPipe, MatButtonModule, MatCardModule, MatIconModule, ImportStatusComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="page">
      <mat-card>
        <mat-card-header><mat-card-title>Импорт товаров</mat-card-title></mat-card-header>
        <mat-card-content>
          <p class="muted">
            Загрузите .xlsx. Импорт выполняется асинхронно в фоне; при повторной загрузке товары
            обновляются по внешнему коду.
          </p>

          <input
            #fileInput
            type="file"
            hidden
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            (change)="onFileSelected(fileInput)"
            data-testid="file-input"
          />
          <button mat-stroked-button (click)="fileInput.click()" [disabled]="busy()">
            <mat-icon>upload_file</mat-icon> Выбрать .xlsx и загрузить
          </button>

          @if (error()) {
            <p class="err" data-testid="import-error">{{ error() }}</p>
          }
        </mat-card-content>
      </mat-card>

      @if (job$ | async; as job) {
        <mat-card class="status">
          <mat-card-content><app-import-status [job]="job" /></mat-card-content>
        </mat-card>
      }
    </div>
  `,
  styles: [
    `
      .status { margin-top: 16px; }
      .err { color: #b00020; }
    `,
  ],
})
export class ImportComponent {
  private readonly importService = inject(ImportService);

  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  private readonly files$ = new Subject<File>();

  /** Загрузка файла → polling статуса до завершения. Новая загрузка отменяет предыдущий polling. */
  protected readonly job$: Observable<ImportJob | null> = this.files$.pipe(
    tap(() => {
      this.busy.set(true);
      this.error.set(null);
    }),
    switchMap((file) =>
      this.importService.upload(file).pipe(
        switchMap((job) => this.importService.watch(job.id)),
        tap((job) => this.busy.set(isActive(job))),
        catchError((err: HttpErrorResponse) => {
          this.busy.set(false);
          this.error.set(this.messageFor(err));
          return of(null);
        }),
      ),
    ),
  );

  protected onFileSelected(input: HTMLInputElement): void {
    const file = input.files?.item(0) ?? null;
    if (file !== null) {
      this.files$.next(file);
    }
    input.value = ''; // чтобы можно было выбрать тот же файл повторно
  }

  private messageFor(err: HttpErrorResponse): string {
    if (err.status === 429) {
      return 'Слишком много запросов на импорт. Подождите немного и повторите.';
    }
    return err.error?.error ?? 'Не удалось загрузить файл';
  }
}
