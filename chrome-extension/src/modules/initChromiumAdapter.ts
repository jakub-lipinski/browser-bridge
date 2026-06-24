import { setBrowserAdapter } from './browserAdapter';
import { ChromiumBrowserAdapter } from './chromiumBrowserAdapter';

setBrowserAdapter(new ChromiumBrowserAdapter());
