/* Original isometric scene. Coordinates and click targets share one layout. */
'use strict';
const C = (key, fallback) => `var(--office-${key}, ${fallback})`;
const P = {
    floor: C('floor', '#192236'), edge: C('floor-edge', '#101827'), grid: C('grid', '#39475f'),
    wall: C('wall', '#28364b'), trim: C('wall-trim', '#576a83'), glass: C('glass', '#78a7b8'),
    desk: C('desk', '#b7acc8'), deskEdge: C('desk-edge', '#776f93'), equipment: C('equipment', '#263449'),
    screen: C('screen', '#88dcdb'), paper: C('paper', '#d4dbe7'), plant: C('plant', '#74b99c'),
    pot: C('pot', '#bc8c82'), shadow: C('shadow', '#030713')
};
const ROOMS = [
    {
        "id": "product",
        "name": "Product & Research",
        "label": "PRODUCT LAB",
        "u": 20,
        "v": 20,
        "w": 280,
        "d": 275
    },
    {
        "id": "leadership",
        "name": "Leadership",
        "label": "LEADERSHIP",
        "u": 320,
        "v": 20,
        "w": 270,
        "d": 275
    },
    {
        "id": "security",
        "name": "Security",
        "label": "SECURITY",
        "u": 620,
        "v": 20,
        "w": 260,
        "d": 275
    },
    {
        "id": "engineering",
        "name": "Engineering",
        "label": "ENGINEERING",
        "u": 20,
        "v": 325,
        "w": 280,
        "d": 270
    },
    {
        "id": "operations",
        "name": "Operations",
        "label": "OPERATIONS",
        "u": 320,
        "v": 325,
        "w": 270,
        "d": 270
    },
    {
        "id": "designqa",
        "name": "Design & QA",
        "label": "DESIGN / QA",
        "u": 620,
        "v": 325,
        "w": 260,
        "d": 270
    }
];
const PEOPLE = [
    [
        "Kai",
        "Product & Research",
        80,
        115,
        "#8cc8ee",
        "#e4b897"
    ],
    [
        "Nova",
        "Product & Research",
        160,
        115,
        "#bbadf5",
        "#a97055"
    ],
    [
        "Orion",
        "Product & Research",
        240,
        115,
        "#e0a274",
        "#ddad8a"
    ],
    [
        "Mira",
        "Product & Research",
        80,
        240,
        "#89c8d9",
        "#b67f60"
    ],
    [
        "Flint",
        "Product & Research",
        160,
        240,
        "#c59a83",
        "#c08c6c"
    ],
    [
        "Peso",
        "Product & Research",
        240,
        240,
        "#bbce89",
        "#e2b391"
    ],
    [
        "Atlas",
        "Leadership",
        375,
        135,
        "#a7a0ff",
        "#e7bfa5"
    ],
    [
        "Sage",
        "Leadership",
        505,
        135,
        "#b9c88c",
        "#c99570"
    ],
    [
        "Tala",
        "Leadership",
        375,
        250,
        "#f4b88e",
        "#c58f70"
    ],
    [
        "Lex",
        "Leadership",
        505,
        250,
        "#8ed3b4",
        "#d8ab8a"
    ],
    [
        "Forge",
        "Engineering",
        80,
        435,
        "#e3ae86",
        "#e2b491"
    ],
    [
        "Vega",
        "Engineering",
        160,
        435,
        "#d7abda",
        "#a97055"
    ],
    [
        "Echo",
        "Engineering",
        240,
        435,
        "#e6a5bd",
        "#d5a482"
    ],
    [
        "Lyra",
        "Engineering",
        80,
        555,
        "#c5a3df",
        "#ad775c"
    ],
    [
        "Beacon",
        "Engineering",
        210,
        555,
        "#e0cd8e",
        "#c69370"
    ],
    [
        "Aegis",
        "Security",
        680,
        140,
        "#8ecac6",
        "#ba8363"
    ],
    [
        "Cipher",
        "Security",
        800,
        245,
        "#97b2d8",
        "#e6bc9d"
    ],
    [
        "Rhea",
        "Operations",
        435,
        435,
        "#9db3eb",
        "#cd9876"
    ],
    [
        "Iris",
        "Design & QA",
        680,
        440,
        "#b6a2ed",
        "#bb8566"
    ],
    [
        "Quinn",
        "Design & QA",
        800,
        555,
        "#93d1c2",
        "#dbad8b"
    ]
].map(([name, room, u, v, color, skin]) => ({ name, room, u, v, color, skin }));

function point(u, v, z = 0) { return { x: 505 + (u - v) * .72, y: 151 + (u + v) * .36 - z }; }
function xy(p) { return `${p.x.toFixed(2)},${p.y.toFixed(2)}`; }
function polygon(points, fill, attrs = '') { return `<polygon points="${points.map(xy).join(' ')}" fill="${fill}" ${attrs}/>`; }
function plane(u, v, w, d, z, fill, attrs = '') {
    return polygon([point(u, v, z), point(u + w, v, z), point(u + w, v + d, z), point(u, v + d, z)], fill, attrs);
}
function box(u, v, w, d, height, top, side = P.deskEdge, base = 0) {
    const a = point(u, v, base + height), b = point(u + w, v, base + height);
    const c = point(u + w, v + d, base + height), e = point(u, v + d, base + height);
    return polygon([b, c, point(u + w, v + d, base), point(u + w, v, base)], side)
        + polygon([e, c, point(u + w, v + d, base), point(u, v + d, base)], side)
        + polygon([a, b, c, e], top);
}
function wall(u1, v1, u2, v2, height, glass = false) {
    const a = point(u1, v1), b = point(u2, v2), c = point(u2, v2, height), d = point(u1, v1, height);
    return `<g${glass ? ' opacity=".47"' : ''}>${polygon([a, b, c, d], glass ? P.glass : P.wall)}
        <path d="M${xy(d)}L${xy(c)}" fill="none" stroke="${P.trim}" stroke-width="3"/>
        <path d="M${xy(a)}L${xy(d)}M${xy(b)}L${xy(c)}" fill="none" stroke="${P.trim}" stroke-width="2"/></g>`;
}
function plant(u, v, size = 1) {
    const p = point(u, v);
    return `<g transform="translate(${p.x} ${p.y}) scale(${size})" class="office-plant">
        <ellipse cy="2" rx="15" ry="6" fill="${P.shadow}" opacity=".2"/>
        <path d="M-9-13H9L6 1H-6Z" fill="${P.pot}"/><ellipse cy="-13" rx="9" ry="3.5" fill="${P.deskEdge}"/>
        <path d="M0-14V-39M0-23L-10-32M0-28L11-39M0-18L12-26" fill="none" stroke="${P.plant}" stroke-width="3"/>
        <path d="M-3-38L-10-45-15-41-9-34H-3ZM2-39L8-49 16-46 12-38ZM-7-29L-18-35-21-29-12-23H-5ZM5-26L14-34 21-29 17-22H7ZM-3-20L-13-24-17-18-8-14H-2Z" fill="${P.plant}"/>
    </g>`;
}
function workstation({ u, v }) {
    const monitor = [point(u - 22, v - 58, 54), point(u + 22, v - 58, 54), point(u + 22, v - 58, 32), point(u - 22, v - 58, 32)];
    const screen = [point(u - 18, v - 58.5, 50), point(u + 18, v - 58.5, 50), point(u + 18, v - 58.5, 36), point(u - 18, v - 58.5, 36)];
    return `<g class="office-workstation">
        ${box(u - 29, v - 64, 4, 4, 25, P.equipment, P.equipment)}
        ${box(u + 25, v - 39, 4, 4, 25, P.equipment, P.equipment)}
        ${box(u - 32, v - 70, 64, 37, 28, P.desk, P.deskEdge)}
        <path d="M${xy(point(u, v - 58, 32))}L${xy(point(u, v - 58, 28))}" stroke="${P.equipment}" stroke-width="4"/>
        ${polygon(monitor, P.equipment)}${polygon(screen, P.screen, 'opacity=".87"')}
        <path d="M${xy(point(u - 13, v - 59, 45))}L${xy(point(u + 9, v - 59, 45))}" stroke="${P.paper}" stroke-width="1.2" opacity=".65"/>
        ${plane(u - 16, v - 47, 29, 9, 28.5, P.equipment)}${plane(u + 20, v - 48, 8, 9, 28.5, P.paper)}
        ${box(u - 13, v - 9, 26, 25, 8, P.equipment, P.edge)}${wall(u - 13, v + 15, u + 13, v + 15, 21)}
    </g>`;
}
function avatar(person, index) {
    const p = point(person.u, person.v), hair = ['#333448', '#41343c', '#342e37', '#56403c'][index % 4];
    return `<g class="office-agent" data-agent="${person.name}" transform="translate(${p.x} ${p.y})">
        <ellipse cy="2" rx="13" ry="5" fill="${P.shadow}" opacity=".3"/>
        <g class="office-avatar" style="animation-delay:-${(index * .37).toFixed(2)}s"><g shape-rendering="crispEdges">
            <rect x="-7" y="-11" width="5" height="11" fill="${P.equipment}"/><rect x="2" y="-11" width="5" height="11" fill="${P.equipment}"/>
            <rect x="-9" y="-2" width="8" height="4" fill="${P.edge}"/><rect x="2" y="-2" width="8" height="4" fill="${P.edge}"/>
            <rect x="-8" y="-27" width="16" height="18" fill="${person.color}"/>
            <rect x="-12" y="-25" width="4" height="13" fill="${person.color}"/><rect x="8" y="-25" width="4" height="13" fill="${person.color}"/>
            <rect x="-12" y="-15" width="4" height="5" fill="${person.skin}"/><rect x="8" y="-15" width="4" height="5" fill="${person.skin}"/>
            <rect x="-3" y="-29" width="6" height="4" fill="${person.skin}"/><rect x="-7" y="-41" width="14" height="14" fill="${person.skin}"/>
            <rect x="-8" y="-43" width="16" height="6" fill="${hair}"/><rect x="-8" y="-38" width="3" height="7" fill="${hair}"/>
            ${index % 3 === 1 ? `<rect x="-9" y="-34" width="3" height="15" fill="${hair}"/>` : ''}
            <rect x="-4" y="-34" width="2" height="2" fill="${P.equipment}"/><rect x="3" y="-34" width="2" height="2" fill="${P.equipment}"/>
            <rect x="-2" y="-18" width="4" height="4" fill="${P.paper}" opacity=".45"/>
        </g></g></g>`;
}
function bookshelf(u, v) {
    let result = box(u, v, 92, 17, 44, P.deskEdge, P.equipment);
    const colors = ['#a89ad0', '#8cbbb8', '#c6a075', '#a3b1d0', '#d0afba'];
    for (let i = 0; i < 9; i++) result += box(u + 4 + i * 9, v + 2, 5, 11, 13 + i % 3 * 3, colors[i % 5], colors[i % 5], 44);
    return result;
}
function roomScene(room) {
    const agents = PEOPLE.filter(person => person.room === room.name).sort((a, b) => a.u + a.v - b.u - b.v);
    let grid = '';
    for (let u = room.u + 40; u < room.u + room.w; u += 40) grid += `<path d="M${xy(point(u, room.v, .6))}L${xy(point(u, room.v + room.d, .6))}"/>`;
    for (let v = room.v + 40; v < room.v + room.d; v += 40) grid += `<path d="M${xy(point(room.u, v, .6))}L${xy(point(room.u + room.w, v, .6))}"/>`;
    const label = point(room.u + 110, room.v + 26, 1);
    const furniture = room.id === 'product' ? bookshelf(room.u + 16, room.v + 12)
        : box(room.u + 17, room.v + 13, 66, 19, 32, P.deskEdge, P.equipment) + plane(room.u + 24, room.v + 17, 23, 11, 32.5, P.paper);
    return `<g class="office-room office-room-${room.id}" data-room="${room.name.replaceAll('&', '&amp;')}">
        ${plane(room.u, room.v, room.w, room.d, .2, C(`room-${room.id}`, '#778899'), `stroke="${P.grid}" stroke-width="1"`)}
        <g fill="none" stroke="${P.grid}" stroke-width=".7" opacity=".33">${grid}</g>
        ${furniture}${plant(room.u + room.w - 19, room.v + 28, .9)}${plant(room.u + 18, room.v + room.d - 17, .8)}
        <text x="${label.x}" y="${label.y}" fill="${C('room-label', '#e9edf4')}" font-family="monospace" font-size="12" font-weight="bold" letter-spacing="2" transform="rotate(26.565 ${label.x} ${label.y})">${room.label}</text>
        ${agents.map(person => workstation(person) + avatar(person, PEOPLE.indexOf(person))).join('')}
    </g>`;
}
const AGENT_LAYOUT = PEOPLE.map(person => {
    const p = point(person.u, person.v, 23);
    return { name: person.name, room: person.room, x: +p.x.toFixed(2), y: +p.y.toFixed(2), color: person.color };
});
function renderOffice() {
    const rooms = ROOMS.slice().sort((a, b) => a.u + a.v - b.u - b.v);
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 850" class="office-scene" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMid meet">
        <ellipse cx="608" cy="611" rx="492" ry="117" fill="${P.shadow}" opacity=".14"/>
        <g class="office-architecture">${box(0, 0, 900, 615, 14, P.floor, P.edge, -14)}
            ${wall(0, 0, 900, 0, 74)}${wall(0, 0, 0, 615, 74)}${plane(2, 2, 896, 611, .05, P.floor)}</g>
        ${rooms.map(roomScene).join('')}
        <g class="office-partitions">
            ${wall(309, 20, 309, 248, 32, true)}${wall(309, 358, 309, 594, 32, true)}
            ${wall(608, 20, 608, 248, 32, true)}${wall(608, 358, 608, 594, 32, true)}
            ${wall(20, 310, 126, 310, 25, true)}${wall(220, 310, 424, 310, 25, true)}
            ${wall(516, 310, 720, 310, 25, true)}${wall(811, 310, 880, 310, 25, true)}</g>
        <g class="office-common-details">${plant(15, 302, 1.15)}${plant(892, 306, 1.15)}
            ${box(868, 559, 22, 38, 40, P.paper, P.deskEdge)}${box(871, 563, 16, 16, 13, P.glass, P.glass, 40)}
            ${plane(405, 602, 86, 8, .5, P.trim, 'opacity=".7"')}${plane(499, 602, 86, 8, .5, P.trim, 'opacity=".7"')}</g>
    </svg>`;
}
module.exports = { renderOffice, AGENT_LAYOUT };
