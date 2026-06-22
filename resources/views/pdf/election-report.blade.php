<!DOCTYPE html>
<html>

<head>
    <title>Official Election Report</title>
    <style>
        @page {
            margin: 1in;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #222;
            line-height: 1.5;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px double #333;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            text-transform: uppercase;
            color: #1a237e;
            font-size: 24px;
        }

        .header p {
            margin: 5px 0;
            font-size: 12px;
            color: #555;
        }

        .stats-container {
            margin-bottom: 25px;
        }

        .stats-table {
            width: 100%;
            border: none;
        }

        .stats-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            text-align: center;
        }

        .stats-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            display: block;
        }

        .stats-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .dept-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .dept-title {
            background: #1a237e;
            color: white;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px 4px 0 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th {
            background-color: #eeeeee;
            color: #333;
            font-size: 11px;
            text-transform: uppercase;
            border: 1px solid #ddd;
            padding: 10px;
        }

        td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            font-size: 12px;
        }

        tr:nth-child(even) {
            background-color: #fafafa;
        }

        .footer-section {
            margin-top: 50px;
            width: 100%;
        }

        .signature-wrapper {
            width: 300px;
            float: right;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 5px;
            font-weight: bold;
        }

        .designation {
            font-size: 11px;
            color: #666;
        }

        .watermark {
            position: absolute;
            top: 45%;
            left: 15%;
            font-size: 80px;
            color: rgba(0, 0, 0, 0.03);
            transform: rotate(-45deg);
            z-index: -1;
        }
    </style>
</head>

<body>
    <div class="watermark">OFFICIAL RESULTS</div>

    <div class="header">
        <h1>Official Election Report</h1>
        <p>Academic Year: {{ $academic_year ?? '2026-2027' }}</p>
        <p>Generated on: {{ $date }}</p>
    </div>

    <div class="stats-container">
        <table class="stats-table">
            <tr>
                <td class="stats-card">
                    <span class="stats-label">Total Votes Cast</span>
                    <span class="stats-value">{{ number_format($totalVotes) }}</span>
                </td>
                <td class="stats-card">
                    <span class="stats-label">Registered Candidates</span>
                    <span class="stats-value">{{ $candidatesCount }}</span>
                </td>
                <td class="stats-card">
                    <span class="stats-label">Voter Turnout</span>
                    <span class="stats-value">{{ $turnout }}</span>
                </td>
            </tr>
        </table>
    </div>

    @php
        $allCandidates = collect($tallyByDept)->flatten(1);
        $winners = $allCandidates->groupBy('position')->map(function ($candidates) {
            $sorted = $candidates->sortByDesc('votes')->values();
            $winnerVotes = $sorted->first()['votes'] ?? 0;
            return $sorted->firstWhere('votes', $winnerVotes);
        });
    @endphp

    <div class="dept-section">
        <div class="dept-title">Winners</div>
        <table>
            <thead>
                <tr>
                    <th style="text-align: left;">Position</th>
                    <th style="text-align: left;">Winner</th>
                    <th style="text-align: left;">Department</th>
                    <th style="text-align: right; width: 120px;">Votes Received</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($winners as $position => $candidate)
                    <tr>
                        <td>{{ $position }}</td>
                        <td>{{ $candidate['label'] }}</td>
                        <td>{{ $candidate['dept'] ?? 'N/A' }}</td>
                        <td style="text-align: right; font-weight: bold;">
                            {{ number_format($candidate['votes']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer-section">
        <div class="signature-wrapper">
            <p style="font-size: 12px; margin-bottom: 40px;">Certified by:</p>
            <div class="signature-line">{{ strtoupper($admin_name ?? (Auth::user()?->name ?? 'System Administrator')) }}</div>
            <div class="designation">System Administrator</div>
        </div>
    </div>
</body>

</html>
