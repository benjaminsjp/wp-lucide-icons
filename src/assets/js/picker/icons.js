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
    'chevron-up': [['path', { d: 'm18 15-6-6-6 6' }]],
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
    star: [
        ['path', { d: 'M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z' }],
    ],
    x: [
        ['path', { d: 'M18 6 6 18' }],
        ['path', { d: 'm6 6 12 12' }],
    ],
    layers: [
        ['path', { d: 'M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z' }],
        ['path', { d: 'M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12' }],
        ['path', { d: 'M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17' }],
    ],
    tag: [
        ['path', { d: 'M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z' }],
        ['circle', { cx: 7.5, cy: 7.5, r: 0.5, fill: 'currentColor' }],
    ],
    info: [
        ['circle', { cx: 12, cy: 12, r: 10 }],
        ['path', { d: 'M12 16v-4' }],
        ['path', { d: 'M12 8h.01' }],
    ],
    'arrow-up-right': [
        ['path', { d: 'M7 7h10v10' }],
        ['path', { d: 'M7 17 17 7' }],
    ],
    'arrow-right': [
        ['path', { d: 'M5 12h14' }],
        ['path', { d: 'm12 5 7 7-7 7' }],
    ],
    'circle-alert': [
        ['circle', { cx: 12, cy: 12, r: 10 }],
        ['line', { x1: 12, x2: 12, y1: 8, y2: 12 }],
        ['line', { x1: 12, x2: 12.01, y1: 16, y2: 16 }],
    ],
    paintbrush: [
        ['path', { d: 'm14.622 17.897-10.68-2.913' }],
        ['path', { d: 'M18.376 2.622a1 1 0 1 1 3.002 3.002L17.36 9.643a.5.5 0 0 0 0 .707l.944.944a2.41 2.41 0 0 1 0 3.408l-.944.944a.5.5 0 0 1-.707 0L8.354 7.348a.5.5 0 0 1 0-.707l.944-.944a2.41 2.41 0 0 1 3.408 0l.944.944a.5.5 0 0 0 .707 0z' }],
        ['path', { d: 'M9 8c-1.804 2.71-3.97 3.46-6.583 3.948a.507.507 0 0 0-.302.819l7.32 8.883a1 1 0 0 0 1.185.204C12.735 20.405 16 16.792 16 15' }],
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