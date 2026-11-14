/**
 * Pure helpers for the guest memory upload form.
 *
 * Kept free of DOM access so the selection summary can be unit tested and the
 * same numbers are shown to the guest that the server will validate against.
 */

const UNITS = ['B', 'KB', 'MB', 'GB'];

/**
 * Format a byte count using Indonesian conventions (comma decimal separator).
 *
 * @param {number} bytes
 * @returns {string}
 */
export function formatFileSize(bytes) {
    const value = Number(bytes);

    if (!Number.isFinite(value) || value <= 0) {
        return '0 KB';
    }

    let unitIndex = 0;
    let size = value;

    while (size >= 1024 && unitIndex < UNITS.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    const decimals = unitIndex === 0 || Number.isInteger(size) ? 0 : 1;

    return `${size.toFixed(decimals).replace('.', ',')} ${UNITS[unitIndex]}`;
}

/**
 * Summarise a file selection against the limits the server will enforce.
 *
 * @param {ArrayLike<{name: string, size: number}>} files
 * @param {{maxFiles?: number, maxFileBytes?: number, maxTotalBytes?: number}} limits
 * @returns {{
 *   items: Array<{name: string, size: number, sizeLabel: string, oversized: boolean}>,
 *   count: number,
 *   totalBytes: number,
 *   totalLabel: string,
 *   exceedsCount: boolean,
 *   exceedsTotal: boolean,
 *   hasRejection: boolean
 * }}
 */
export function describeSelection(files, limits = {}) {
    const maxFiles = Number(limits.maxFiles) > 0 ? Number(limits.maxFiles) : Number.POSITIVE_INFINITY;
    const maxFileBytes = Number(limits.maxFileBytes) > 0 ? Number(limits.maxFileBytes) : Number.POSITIVE_INFINITY;
    const maxTotalBytes = Number(limits.maxTotalBytes) > 0 ? Number(limits.maxTotalBytes) : Number.POSITIVE_INFINITY;

    const items = Array.from(files ?? []).map((file) => {
        const size = Number(file?.size) || 0;

        return {
            name: String(file?.name ?? ''),
            size,
            sizeLabel: formatFileSize(size),
            oversized: size > maxFileBytes,
        };
    });

    const totalBytes = items.reduce((sum, item) => sum + item.size, 0);
    const exceedsCount = items.length > maxFiles;
    const exceedsTotal = totalBytes > maxTotalBytes;

    return {
        items,
        count: items.length,
        totalBytes,
        totalLabel: formatFileSize(totalBytes),
        exceedsCount,
        exceedsTotal,
        hasRejection: exceedsCount || exceedsTotal || items.some((item) => item.oversized),
    };
}
