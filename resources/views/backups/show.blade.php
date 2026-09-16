<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Backup Details</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f5f7fb;
        }

        .details-card {
            border: none;
            border-radius: 16px;
            box-shadow:
                0 4px 18px rgba(0, 0, 0, 0.06);
        }

        .detail-icon {
            width: 55px;
            height: 55px;

            border-radius: 14px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #eef2ff;

            font-size: 25px;
        }

        .detail-item {
            padding: 18px;

            border-radius: 12px;

            background: #f8fafc;

            height: 100%;
        }

        .detail-label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .detail-value {
            font-weight: 600;
            word-break: break-word;
        }

    </style>

</head>


<body>

<div class="container py-5">


    {{-- Header --}}

    <div
        class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3"
    >

        <div>

            <h2 class="fw-bold mb-1">
                Backup Details
            </h2>

            <p class="text-muted mb-0">
                Detailed information about this backup archive
            </p>

        </div>


        <a
            href="{{ route('backups.index') }}"
            class="btn btn-outline-secondary"
        >
            ← Back to Backups
        </a>

    </div>


    {{-- Main card --}}

    <div class="card details-card">

        <div class="card-body p-4">


            {{-- Backup heading --}}

            <div
                class="d-flex align-items-center mb-4"
            >

                <div class="detail-icon me-3">
                    📦
                </div>

                <div>

                    <h4 class="fw-bold mb-1">
                        {{ $backup['name'] }}
                    </h4>

                    <div class="text-muted">
                        {{ $backup['extension'] }} backup archive
                    </div>

                </div>

            </div>


            {{-- Details --}}

            <div class="row g-4">


                {{-- Size --}}

                <div class="col-md-4">

                    <div class="detail-item">

                        <div class="detail-label">
                            File Size
                        </div>

                        <div class="detail-value">
                            {{ $backup['size_formatted'] }}
                        </div>

                    </div>

                </div>


                {{-- Created --}}

                <div class="col-md-4">

                    <div class="detail-item">

                        <div class="detail-label">
                            Created Date
                        </div>

                        <div class="detail-value">
                            {{ $backup['formatted_date'] }}
                        </div>

                    </div>

                </div>


                {{-- Time --}}

                <div class="col-md-4">

                    <div class="detail-item">

                        <div class="detail-label">
                            Created Time
                        </div>

                        <div class="detail-value">
                            {{ $backup['formatted_time'] }}
                        </div>

                    </div>

                </div>


                {{-- Age --}}

                <div class="col-md-4">

                    <div class="detail-item">

                        <div class="detail-label">
                            Backup Age
                        </div>

                        <div class="detail-value">
                            {{ $backup['age'] }}
                        </div>

                    </div>

                </div>


                {{-- Disk --}}

                <div class="col-md-4">

                    <div class="detail-item">

                        <div class="detail-label">
                            Storage Disk
                        </div>

                        <div class="detail-value">
                            {{ $backup['storage_disk'] }}
                        </div>

                    </div>

                </div>


                {{-- Format --}}

                <div class="col-md-4">

                    <div class="detail-item">

                        <div class="detail-label">
                            File Format
                        </div>

                        <div class="detail-value">
                            {{ $backup['extension'] }}
                        </div>

                    </div>

                </div>


                {{-- Full path --}}

                <div class="col-12">

                    <div class="detail-item">

                        <div class="detail-label">
                            Storage Path
                        </div>

                        <div class="detail-value">
                            {{ $backup['path'] }}
                        </div>

                    </div>

                </div>

            </div>


            {{-- Actions --}}

            <div
                class="d-flex gap-2 mt-4 flex-wrap"
            >

                <a
                    href="{{ route('backups.download', ['filename' => $backup['name']]) }}"
                    class="btn btn-primary"
                >
                    ⬇ Download Backup
                </a>


                <form
                    action="{{ route('backups.destroy', ['filename' => $backup['name']]) }}"
                    method="POST"
                >

                    @csrf

                    @method('DELETE')

                    <button
                        type="submit"
                        class="btn btn-outline-danger"
                        onclick="return confirm('Are you sure you want to permanently delete this backup?')"
                    >
                        🗑 Delete Backup
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

</body>

</html>