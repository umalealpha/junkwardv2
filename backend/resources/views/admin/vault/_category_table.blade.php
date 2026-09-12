@php
$categoryIcons = [
    'ai'         => 'fa-robot',
    'payment'    => 'fa-credit-card',
    'sms'        => 'fa-comment-dots',
    'cloud'      => 'fa-cloud',
    'compliance' => 'fa-gavel',
    'tracking'   => 'fa-map-marker-alt',
    'email'      => 'fa-envelope',
    'other'      => 'fa-key',
];
$icon = $categoryIcons[$catKey] ?? 'fa-key';
@endphp

<div class="mb-4">
    <h6 class="font-weight-bold text-uppercase text-muted mb-3" style="letter-spacing:0.05em;font-size:0.8rem;">
        <i class="fa {{ $icon }} mr-2" style="color:#7c3aed;"></i>{{ $catLabel }}
    </h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover" style="min-width:700px;">
            <thead class="thead-light">
                <tr>
                    <th style="width:22%;">Label</th>
                    <th style="width:18%;">Key Name</th>
                    <th style="width:28%;">Value</th>
                    <th style="width:24%;">Description</th>
                    <th style="width:8%;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                <tr data-key="{{ $row->setting_key }}">
                    <td class="align-middle">
                        <span class="font-weight-semibold">{{ $row->label }}</span>
                        @if ($row->is_secret)
                            <span class="badge badge-warning badge-sm ml-1" style="font-size:0.65rem;">secret</span>
                        @endif
                    </td>
                    <td class="align-middle">
                        <code class="text-dark" style="font-size:0.8rem;">{{ $row->setting_key }}</code>
                    </td>
                    <td class="align-middle value-cell">
                        <div class="value-mask">
                            @if ($row->setting_value)
                                <span class="text-muted" style="letter-spacing:0.15em;">••••••••••</span>
                            @else
                                <span class="text-muted font-italic">Not set</span>
                            @endif
                        </div>
                        @if ($row->is_secret || $row->setting_value)
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-reveal mt-1"
                                    data-key="{{ $row->setting_key }}"
                                    style="font-size:0.75rem;padding:2px 8px;">
                                <i class="fa fa-eye mr-1"></i>Reveal
                            </button>
                        @endif
                    </td>
                    <td class="align-middle text-muted" style="font-size:0.85rem;">
                        {{ $row->description ?? '' }}
                    </td>
                    <td class="align-middle text-center">
                        <button type="button" class="btn btn-xs btn-outline-primary btn-edit-cred"
                                data-key="{{ $row->setting_key }}"
                                title="Edit value"
                                style="font-size:0.75rem;padding:2px 8px;">
                            <i class="fa fa-pencil-alt"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
