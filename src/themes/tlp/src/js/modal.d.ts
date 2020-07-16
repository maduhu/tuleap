/*
 * Copyright (c) Enalean, 2019 - present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

interface ModalOptions {
    keyboard?: boolean;
    destroy_on_hide?: boolean;
}
declare class Modal {
    constructor(doc: Document, element: Element, options?: ModalOptions);

    toggle(): void;
    show(): void;
    hide(): void;
    destroy(): void;
    addEventListener(type: string, listener: (evt: Event) => void): void;
    removeEventListener(type: string, listener: (evt: Event) => void): void;
}
export function modal(doc: Document, element: Element, options?: ModalOptions): Modal;

export const EVENT_TLP_MODAL_SHOWN = "tlp-modal-shown";
export const EVENT_TLP_MODAL_HIDDEN = "tlp-modal-hidden";

export const BACKDROP_ID = "tlp-modal-backdrop";
export const BACKDROP_SHOWN_CLASS_NAME = "tlp-modal-backdrop-shown";
export const MODAL_DISPLAY_CLASS_NAME = "tlp-modal-display";
export const MODAL_SHOWN_CLASS_NAME = "tlp-modal-shown";
