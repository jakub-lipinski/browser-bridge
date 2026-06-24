import { setBrowserAdapter } from '../../../chrome-extension/src/modules/browserAdapter';
import { SafariBrowserAdapter } from './safariBrowserAdapter';

setBrowserAdapter(new SafariBrowserAdapter());
