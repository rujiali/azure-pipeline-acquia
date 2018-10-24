import { createElement } from 'react';
import { render } from 'react-dom';

import TransitionSet from './TransitionSet';

const target = document.querySelector('TransitionSet');

if (target)
{
    const dataElement = target.querySelector('input[ type="hidden" ]');

    const element = createElement(TransitionSet, {
        states: JSON.parse(
            target.getAttribute('states')
        ),
        transitions: JSON.parse(
            dataElement.value,
        ),
        input:
            dataElement.name,
        step:
            target.getAttribute('step') || 60,
        since:
            target.getAttribute('since'),
    });
    render(element, target);
}
