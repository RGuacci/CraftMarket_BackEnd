<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',

            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id|distinct',

            'images' => 'nullable|array|max:5',
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp,gif,avif,bmp',
                'max:5120',
            ],

            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|distinct',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome del prodotto è obbligatorio.',
            'name.string' => 'Il nome deve essere un testo valido.',
            'name.max' => 'Il nome non può superare i 255 caratteri.',

            'description.required' => 'La descrizione è obbligatoria.',
            'description.string' => 'La descrizione deve essere un testo valido.',

            'price.required' => 'Il prezzo è obbligatorio.',
            'price.numeric' => 'Il prezzo deve essere un numero valido.',
            'price.min' => 'Il prezzo non può essere negativo.',

            'stock.required' => 'La quantità disponibile è obbligatoria.',
            'stock.integer' => 'La quantità deve essere un numero intero.',
            'stock.min' => 'La quantità non può essere negativa.',

            'categories.array' => 'Il formato delle categorie non è valido.',
            'categories.*.exists' => 'Una delle categorie selezionate non è valida.',
            'categories.*.distinct' => 'Non puoi selezionare la stessa categoria più volte.',

            'images.array' => 'Il formato delle immagini non è valido.',
            'images.max' => 'Puoi caricare al massimo 5 immagini.',
            'images.*.image' => 'Ogni file caricato deve essere un’immagine valida.',
            'images.*.mimes' => 'Le immagini devono essere in formato JPG, JPEG, PNG, WEBP, GIF, AVIF o BMP.',
            'images.*.max' => 'Ogni immagine non può superare i 5 MB.',

            'remove_images.array' => 'Il formato delle immagini da rimuovere non è valido.',
            'remove_images.*.integer' => 'L’identificativo di un’immagine da rimuovere non è valido.',
            'remove_images.*.distinct' => 'Non puoi indicare la stessa immagine più volte.',
        ];
    }
}
