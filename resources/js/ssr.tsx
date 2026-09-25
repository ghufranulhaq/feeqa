/* prettier-ignore */
import {
createInertiaApp,
type ResolvedComponent
} from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        resolve: (name) => {
            const pages = import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx', {
                eager: true,
            });
            return pages[`./pages/${name}.tsx`].default;
        },
        // prettier-ignore
        setup: ({ App, props }) => <App {...props} />,
    }),
);
