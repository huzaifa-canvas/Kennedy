@if($item->isArchivedByPlan())
  <span class="badge bg-label-warning ms-1" title="Archived on {{ $item->archived_at?->format('d M Y') }} when the account moved to Free">Archived (Free limit)</span>
@elseif(! $active)
  <span class="badge bg-label-secondary ms-1">Paused</span>
@endif
