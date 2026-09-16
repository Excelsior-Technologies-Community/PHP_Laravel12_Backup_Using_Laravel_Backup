<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Backup Management</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f5f7fb;
        }

        .dashboard-card,
        .stat-card,
        .table-card {
            border: none;
            border-radius: 14px;
            box-shadow:
                0 4px 18px rgba(0, 0, 0, 0.06);
        }

        .stat-card {
            transition: 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 22px;

            background: #eef2ff;
        }

        .backup-name {
            font-weight: 600;
        }

        .action-button {
            min-width: 100px;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .command-output {
            white-space: pre-wrap;
            max-height: 250px;
            overflow-y: auto;

            background: #111827;
            color: #f9fafb;

            border-radius: 8px;
            padding: 15px;

            font-size: 13px;
        }

        .health-card {
            border-radius: 14px;
            border: none;
        }

        .health-healthy {
            background: #d1fae5;
            color: #065f46;
        }

        .health-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .health-critical {
            background: #fee2e2;
            color: #991b1b;
        }

        .health-badge {
            padding: 8px 14px;
            border-radius: 30px;
            font-weight: 600;
        }

        .progress {
            height: 9px;
            border-radius: 20px;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
        }

        .selected-row {
            background: #f8fafc;
        }

    </style>

</head>


<body>

<div class="container py-4">


    {{-- Header --}}

    <div
        class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3"
    >

        <div>

            <h2 class="fw-bold mb-1">
                Backup Management
            </h2>

            <p class="text-muted mb-0">
                Manage Laravel application and database backups
            </p>

        </div>


        <div class="d-flex gap-2">

            <form
                action="{{ route('backups.run') }}"
                method="POST"
            >

                @csrf

                <button
                    type="submit"
                    class="btn btn-primary action-button"
                    onclick="return confirm('Start a new backup now?')"
                >
                    ▶ Run Backup
                </button>

            </form>


            <form
                action="{{ route('backups.cleanup') }}"
                method="POST"
            >

                @csrf

                <button
                    type="submit"
                    class="btn btn-outline-danger action-button"
                    onclick="return confirm('Run backup cleanup now?')"
                >
                    🧹 Cleanup
                </button>

            </form>

        </div>

    </div>


    {{-- Success --}}

    @if(session('success'))

        <div class="alert alert-success alert-dismissible fade show">

            <strong>Success!</strong>

            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    @endif


    {{-- Error --}}

    @if(session('error'))

        <div class="alert alert-danger alert-dismissible fade show">

            <strong>Error!</strong>

            {{ session('error') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    @endif


    {{-- Command Output --}}

    @if(session('command_output'))

        <div class="card dashboard-card mb-4">

            <div class="card-header bg-dark text-white">
                Command Output
            </div>

            <div class="card-body">

                <div class="command-output">
                    {{ session('command_output') }}
                </div>

            </div>

        </div>

    @endif


    {{-- Statistics --}}

    <div class="row g-4 mb-4">


        {{-- Total --}}

        <div class="col-md-4 col-lg-2">

            <div class="card stat-card h-100">

                <div class="card-body">

                    <div class="stat-icon mb-3">
                        📦
                    </div>

                    <div class="text-muted small">
                        Total Backups
                    </div>

                    <h3 class="fw-bold">
                        {{ $totalBackups }}
                    </h3>

                </div>

            </div>

        </div>


        {{-- Total Size --}}

        <div class="col-md-4 col-lg-2">

            <div class="card stat-card h-100">

                <div class="card-body">

                    <div class="stat-icon mb-3">
                        💾
                    </div>

                    <div class="text-muted small">
                        Total Size
                    </div>

                    <h3 class="fw-bold">
                        {{ $totalSize > 0 ? number_format($totalSize / 1024 / 1024, 2) . ' MB' : '0 MB' }}
                    </h3>

                </div>

            </div>

        </div>


        {{-- Average --}}

        <div class="col-md-4 col-lg-2">

            <div class="card stat-card h-100">

                <div class="card-body">

                    <div class="stat-icon mb-3">
                        📊
                    </div>

                    <div class="text-muted small">
                        Average Size
                    </div>

                    <h3 class="fw-bold">
                        {{ $averageSize > 0 ? number_format($averageSize / 1024 / 1024, 2) . ' MB' : '0 MB' }}
                    </h3>

                </div>

            </div>

        </div>


        {{-- Latest --}}

        <div class="col-md-4 col-lg-2">

            <div class="card stat-card h-100">

                <div class="card-body">

                    <div class="stat-icon mb-3">
                        🕒
                    </div>

                    <div class="text-muted small">
                        Latest Backup
                    </div>

                    @if($latestBackupData)

                        <h6 class="fw-bold mb-1">
                            {{ $latestBackupData['age'] }}
                        </h6>

                        <small class="text-muted">
                            {{ $latestBackupData['size'] }}
                        </small>

                    @else

                        <div class="text-muted">
                            None
                        </div>

                    @endif

                </div>

            </div>

        </div>


        {{-- Oldest --}}

        <div class="col-md-4 col-lg-2">

            <div class="card stat-card h-100">

                <div class="card-body">

                    <div class="stat-icon mb-3">
                        🗓️
                    </div>

                    <div class="text-muted small">
                        Oldest Backup
                    </div>

                    @if($oldestBackupData)

                        <h6 class="fw-bold mb-1">
                            {{ $oldestBackupData['date'] }}
                        </h6>

                        <small class="text-muted">
                            {{ $oldestBackupData['size'] }}
                        </small>

                    @else

                        <div class="text-muted">
                            None
                        </div>

                    @endif

                </div>

            </div>

        </div>


        {{-- Health --}}

        <div class="col-md-4 col-lg-2">

            <div class="card stat-card h-100">

                <div class="card-body">

                    <div class="stat-icon mb-3">
                        🩺
                    </div>

                    <div class="text-muted small">
                        Backup Health
                    </div>

                    <span
                        class="health-badge health-{{ $health['status'] }}"
                    >
                        {{ $health['label'] }}
                    </span>

                </div>

            </div>

        </div>

    </div>


    {{-- Storage Health --}}

    <div class="card dashboard-card mb-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-md-4">

                    <h5 class="fw-bold mb-1">
                        💽 Storage Health
                    </h5>

                    <p class="text-muted mb-0">
                        Disk usage for backup storage
                    </p>

                </div>


                <div class="col-md-8">

                    <div
                        class="d-flex justify-content-between mb-2"
                    >

                        <span>
                            Used:
                            <strong>
                                {{ $storageStats['used'] }}
                            </strong>
                        </span>

                        <span>
                            {{ $storageStats['usage_percent'] }}%
                        </span>

                    </div>

                    <div class="progress">

                        <div
                            class="progress-bar"
                            role="progressbar"
                            style="width: {{ min(100, $storageStats['usage_percent']) }}%"
                        ></div>

                    </div>

                    <div class="d-flex justify-content-between mt-2">

                        <small class="text-muted">
                            Free:
                            {{ $storageStats['free'] }}
                        </small>

                        <small class="text-muted">
                            Total:
                            {{ $storageStats['total'] }}
                        </small>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- Health Message --}}

    <div
        class="alert
        @if($health['status'] === 'healthy')
            alert-success
        @elseif($health['status'] === 'warning')
            alert-warning
        @else
            alert-danger
        @endif
        mb-4"
    >

        <strong>
            🩺 {{ $health['label'] }}
        </strong>

        —
        {{ $health['message'] }}

    </div>


    {{-- Backup History --}}

    <div class="card table-card">

        <div class="card-body">

            <div
                class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"
            >

                <div>

                    <h5 class="fw-bold mb-1">
                        Backup History
                    </h5>

                    <small class="text-muted">
                        {{ $totalFiltered }} backup(s) found
                    </small>

                </div>


                {{-- CSV --}}

                <a
                    href="{{ route('backups.index', array_merge(request()->query(), ['export' => 'csv'])) }}"
                    class="btn btn-outline-success"
                >
                    📄 Export CSV
                </a>

            </div>


            {{-- Filters --}}

            <form
                action="{{ route('backups.index') }}"
                method="GET"
                class="row g-2 mb-4"
            >


                {{-- Search --}}

                <div class="col-md-4">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search backup filename..."
                        value="{{ request('search') }}"
                    >

                </div>


                {{-- Date Preset --}}

                <div class="col-md-2">

                    <select
                        name="date_preset"
                        class="form-select"
                    >

                        <option
                            value="all"
                            {{ $datePreset === 'all' ? 'selected' : '' }}
                        >
                            All Dates
                        </option>

                        <option
                            value="today"
                            {{ $datePreset === 'today' ? 'selected' : '' }}
                        >
                            Today
                        </option>

                        <option
                            value="7days"
                            {{ $datePreset === '7days' ? 'selected' : '' }}
                        >
                            Last 7 Days
                        </option>

                        <option
                            value="30days"
                            {{ $datePreset === '30days' ? 'selected' : '' }}
                        >
                            Last 30 Days
                        </option>

                    </select>

                </div>


                {{-- Exact Date --}}

                <div class="col-md-2">

                    <input
                        type="date"
                        name="date"
                        class="form-control"
                        value="{{ request('date') }}"
                    >

                </div>


                {{-- Size --}}

                <div class="col-md-2">

                    <select
                        name="size_filter"
                        class="form-select"
                    >

                        <option
                            value="all"
                            {{ $sizeFilter === 'all' ? 'selected' : '' }}
                        >
                            All Sizes
                        </option>

                        <option
                            value="small"
                            {{ $sizeFilter === 'small' ? 'selected' : '' }}
                        >
                            Small &lt; 10 MB
                        </option>

                        <option
                            value="medium"
                            {{ $sizeFilter === 'medium' ? 'selected' : '' }}
                        >
                            Medium 10–100 MB
                        </option>

                        <option
                            value="large"
                            {{ $sizeFilter === 'large' ? 'selected' : '' }}
                        >
                            Large &gt; 100 MB
                        </option>

                    </select>

                </div>


                {{-- Sort --}}

                <div class="col-md-2">

                    <select
                        name="sort"
                        class="form-select"
                    >

                        <option
                            value="newest"
                            {{ $sort === 'newest' ? 'selected' : '' }}
                        >
                            Newest First
                        </option>

                        <option
                            value="oldest"
                            {{ $sort === 'oldest' ? 'selected' : '' }}
                        >
                            Oldest First
                        </option>

                        <option
                            value="largest"
                            {{ $sort === 'largest' ? 'selected' : '' }}
                        >
                            Largest First
                        </option>

                        <option
                            value="smallest"
                            {{ $sort === 'smallest' ? 'selected' : '' }}
                        >
                            Smallest First
                        </option>

                    </select>

                </div>


                {{-- Per page --}}

                <div class="col-md-2">

                    <select
                        name="per_page"
                        class="form-select"
                    >

                        <option
                            value="5"
                            {{ $perPage === 5 ? 'selected' : '' }}
                        >
                            5 per page
                        </option>

                        <option
                            value="10"
                            {{ $perPage === 10 ? 'selected' : '' }}
                        >
                            10 per page
                        </option>

                        <option
                            value="20"
                            {{ $perPage === 20 ? 'selected' : '' }}
                        >
                            20 per page
                        </option>

                        <option
                            value="50"
                            {{ $perPage === 50 ? 'selected' : '' }}
                        >
                            50 per page
                        </option>

                    </select>

                </div>


                <div class="col-md-2">

                    <button
                        type="submit"
                        class="btn btn-dark w-100"
                    >
                        🔎 Filter
                    </button>

                </div>


                <div class="col-md-2">

                    <a
                        href="{{ route('backups.index') }}"
                        class="btn btn-outline-secondary w-100"
                    >
                        Reset
                    </a>

                </div>

            </form>


            @if($backups->count())

                {{-- Bulk Delete --}}

                <form
                    action="{{ route('backups.bulk-delete') }}"
                    method="POST"
                    id="bulkDeleteForm"
                >

                    @csrf

                    <div
                        class="d-flex justify-content-between align-items-center mb-3"
                    >

                        <div>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                id="bulkDeleteButton"
                                onclick="submitBulkDelete()"
                                disabled
                            >
                                🗑 Delete Selected
                            </button>

                            <span
                                class="text-muted ms-2"
                                id="selectedCount"
                            >
                                0 selected
                            </span>

                        </div>

                    </div>


                    <div class="table-responsive">

                        <table
                            class="table table-hover align-middle"
                        >

                            <thead class="table-light">

                            <tr>

                                <th>

                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="selectAll"
                                    >

                                </th>

                                <th>#</th>

                                <th>Backup File</th>

                                <th>Size</th>

                                <th>Created</th>

                                <th>Age</th>

                                <th class="text-end">
                                    Actions
                                </th>

                            </tr>

                            </thead>


                            <tbody>

                            @foreach($backups as $index => $backup)

                                <tr>

                                    <td>

                                        <input
                                            type="checkbox"
                                            class="form-check-input backup-checkbox"
                                            name="filenames[]"
                                            value="{{ $backup['name'] }}"
                                        >

                                    </td>


                                    <td>

                                        {{
                                            (($currentPage - 1) * $perPage)
                                            + $index
                                            + 1
                                        }}

                                    </td>


                                    <td>

                                        <div class="backup-name">
                                            📦 {{ $backup['name'] }}
                                        </div>

                                    </td>


                                    <td>

                                        {{ $backup['size_formatted'] }}

                                    </td>


                                    <td>

                                        <div>
                                            {{ date('d M Y', $backup['last_modified']) }}
                                        </div>

                                        <small class="text-muted">
                                            {{ date('h:i:s A', $backup['last_modified']) }}
                                        </small>

                                    </td>


                                    <td>

                                        <span class="text-muted">
                                            {{
                                                \Carbon\Carbon::createFromTimestamp(
                                                    $backup['last_modified']
                                                )->diffForHumans()
                                            }}
                                        </span>

                                    </td>


                                    <td>

                                        <div
                                            class="d-flex justify-content-end gap-2 flex-wrap"
                                        >

                                            {{-- Details --}}

                                            <a
                                                href="{{ route('backups.show', ['filename' => $backup['name']]) }}"
                                                class="btn btn-sm btn-outline-dark"
                                            >
                                                👁 Details
                                            </a>


                                            {{-- Download --}}

                                            <a
                                                href="{{ route('backups.download', ['filename' => $backup['name']]) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                ⬇ Download
                                            </a>


                                            {{-- Delete --}}

                                            <form
                                                action="{{ route('backups.destroy', ['filename' => $backup['name']]) }}"
                                                method="POST"
                                            >

                                                @csrf

                                                @method('DELETE')

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Are you sure you want to permanently delete this backup?')"
                                                >
                                                    🗑 Delete
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            @endforeach

                            </tbody>

                        </table>

                    </div>

                </form>


                {{-- Pagination --}}

                @if($totalPages > 1)

                    <div
                        class="d-flex justify-content-center mt-4"
                    >

                        <nav>

                            <ul class="pagination">

                                @for(
                                    $page = 1;
                                    $page <= $totalPages;
                                    $page++
                                )

                                    @php

                                        $paginationQuery =
                                            array_merge(
                                                $queryParameters,
                                                [
                                                    'page' => $page
                                                ]
                                            );

                                    @endphp

                                    <li
                                        class="page-item
                                        {{ $currentPage === $page ? 'active' : '' }}"
                                    >

                                        <a
                                            class="page-link"
                                            href="{{ route('backups.index', $paginationQuery) }}"
                                        >
                                            {{ $page }}
                                        </a>

                                    </li>

                                @endfor

                            </ul>

                        </nav>

                    </div>

                @endif

            @else

                <div class="empty-state">

                    <div style="font-size: 50px;">
                        📦
                    </div>

                    <h5 class="fw-bold mt-3">
                        No backups found
                    </h5>

                    <p class="text-muted">
                        Run your first backup to see it here.
                    </p>

                    <form
                        action="{{ route('backups.run') }}"
                        method="POST"
                    >

                        @csrf

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            ▶ Create First Backup
                        </button>

                    </form>

                </div>

            @endif

        </div>

    </div>

</div>


<script>

    const selectAll =
        document.getElementById('selectAll');

    const checkboxes =
        document.querySelectorAll(
            '.backup-checkbox'
        );

    const bulkDeleteButton =
        document.getElementById(
            'bulkDeleteButton'
        );

    const selectedCount =
        document.getElementById(
            'selectedCount'
        );


    function updateSelectedCount()
    {
        const selected =
            document.querySelectorAll(
                '.backup-checkbox:checked'
            ).length;

        selectedCount.innerText =
            selected + ' selected';

        bulkDeleteButton.disabled =
            selected === 0;
    }


    if (selectAll) {

        selectAll.addEventListener(
            'change',
            function () {

                checkboxes.forEach(
                    checkbox => {

                        checkbox.checked =
                            selectAll.checked;

                        checkbox
                            .closest('tr')
                            .classList.toggle(
                                'selected-row',
                                checkbox.checked
                            );
                    }
                );

                updateSelectedCount();
            }
        );
    }


    checkboxes.forEach(
        checkbox => {

            checkbox.addEventListener(
                'change',
                function () {

                    this
                        .closest('tr')
                        .classList.toggle(
                            'selected-row',
                            this.checked
                        );

                    updateSelectedCount();
                }
            );
        }
    );


    function submitBulkDelete()
    {
        const selected =
            document.querySelectorAll(
                '.backup-checkbox:checked'
            ).length;

        if (selected === 0) {

            alert(
                'Please select at least one backup.'
            );

            return;
        }

        if (
            confirm(
                'Are you sure you want to permanently delete ' +
                selected +
                ' selected backup(s)?'
            )
        ) {

            document
                .getElementById(
                    'bulkDeleteForm'
                )
                .submit();
        }
    }

</script>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>