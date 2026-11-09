import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, switchMap, takeWhile, timer } from 'rxjs';
import { ImportJob, isActive } from '../models/import-job';

@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly http = inject(HttpClient);

  upload(file: File): Observable<ImportJob> {
    const form = new FormData();
    form.append('file', file, file.name);
    return this.http.post<ImportJob>('/api/imports', form);
  }

  get(id: number): Observable<ImportJob> {
    return this.http.get<ImportJob>(`/api/imports/${id}`);
  }

  latest(): Observable<ImportJob> {
    return this.http.get<ImportJob>('/api/imports/latest');
  }

  /** Polling статуса: эмитит каждые 1.5с, пока задача не завершится (последнее значение включительно). */
  watch(id: number, intervalMs = 1500): Observable<ImportJob> {
    return timer(0, intervalMs).pipe(
      switchMap(() => this.get(id)),
      takeWhile(isActive, true),
    );
  }
}
