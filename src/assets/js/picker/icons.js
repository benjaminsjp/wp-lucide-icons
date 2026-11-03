/**
 * Minimal inline Lucide icons (lucide-static, ISC license) used by the admin UI chrome.
 * Kept here so admin screens don't depend on the icon REST API for their own chrome.
 */
const ICONS = {
    search: [
        ['path', { d: 'm21 21-4.34-4.34' }],
        ['circle', { cx: 11, cy: 11, r: 8 }],
    ],
    'chevron-down': [['path', { d: 'm6 9 6 6 6-6' }]],
    copy: [
        ['rect', { width: 14, height: 14, x: 8, y: 8, rx: 2, ry: 2 }],
        ['path', { d: 'M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2' }],
    ],
    check: [['path', { d: 'M20 6 9 17l-5-5' }]],
    'mouse-pointer-click': [
        ['path', { d: 'M14 4.1 12 6' }],
        ['path', { d: 'm5.1 8-2.9-.8' }],
        ['path', { d: 'm6 12-1.9 2' }],
        ['path', { d: 'M7.2 2.2 8 5.1' }],
        ['path', { d: 'M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z' }],
    ],
};

export default function LucideSvg({ name, size = 16, className = '' }) {
    const nodes = ICONS[name];
    if (!nodes) {
        return null;
    }

    return (
        <svg
            className={`wpicons__admin-icon${className ? ` ${className}` : ''}`}
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
        >
            {nodes.map(([Tag, attrs], index) => <Tag key={index} {...attrs} />)}
        </svg>
    );
}
