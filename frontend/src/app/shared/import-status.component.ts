import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { ImportJob, isActive } from '../models/import-job';

const LABELS: Record<ImportJob['status'], string> = {
  pending: 'В очереди',
  processing: 'Импорт выполняется',
  completed: 'Импорт завершён',
  failed: 'Импорт не выполнен',
};

/** Индикатор прогресса + отчёт об ошибках импорта (используется на странице импорта и в карточке товара). */
@Component({
  selector: 'app-import-status',
  standalone: true,
  imports: [MatProgressBarModule, MatExpansionModule, MatIconModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @let j = job();
    <div class="head">
      <strong>{{ label() }}</strong>
      <span class="muted">{{ j.originalName }}</span>
    </div>

    @if (active()) {
      <mat-progress-bar
        [mode]="j.totalRows > 0 ? 'determinate' : 'indeterminate'"
        [value]="j.progress"
      />
    }

    <p class="counts">
      Строк: {{ j.processedRows }} / {{ j.totalRows }} · успешно: {{ j.succeededRows }} · с ошибками:
      {{ j.failedRows }}
    </p>

    @if (j.errorMessage) {
      <p class="fatal"><mat-icon>error</mat-icon> {{ j.errorMessage }}</p>
    }

    @if (j.report.length > 0) {
      <mat-expansion-panel>
        <mat-expansion-panel-header>
          <mat-panel-title>Отчёт ({{ j.report.length }})</mat-panel-title>
        </mat-expansion-panel-header>
        <ul class="report">
          @for (entry of j.report; track $index) {
            <li [class.error]="entry.level === 'error'">
              <mat-icon>{{ entry.level === 'error' ? 'error' : 'warning' }}</mat-icon>
              <span>строка {{ entry.row }}: {{ entry.message }}</span>
            </li>
          }
        </ul>
      </mat-expansion-panel>
    }
  `,
  styles: [
    `
      .head { display: flex; gap: 12px; align-items: baseline; margin-bottom: 8px; }
      .counts { margin: 8px 0; }
      .fatal { color: #b00020; display: flex; align-items: center; gap: 8px; }
      .report { list-style: none; margin: 0; padding: 0; max-height: 320px; overflow: auto; }
      .report li { display: flex; gap: 8px; align-items: center; padding: 4px 0; color: #8a6d00; }
      .report li.error { color: #b00020; }
    `,
  ],
})
export class ImportStatusComponent {
  readonly job = input.required<ImportJob>();
  protected readonly label = computed(() => LABELS[this.job().status]);
  protected readonly active = computed(() => isActive(this.job()));
}
