package com.kyusui.app.data.datastore;

import androidx.datastore.core.DataStore;
import androidx.datastore.preferences.core.Preferences;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;

@ScopeMetadata("javax.inject.Singleton")
@QualifierMetadata
@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava"
})
public final class PreferencesRepository_Factory implements Factory<PreferencesRepository> {
  private final Provider<DataStore<Preferences>> dataStoreProvider;

  public PreferencesRepository_Factory(Provider<DataStore<Preferences>> dataStoreProvider) {
    this.dataStoreProvider = dataStoreProvider;
  }

  @Override
  public PreferencesRepository get() {
    return newInstance(dataStoreProvider.get());
  }

  public static PreferencesRepository_Factory create(
      Provider<DataStore<Preferences>> dataStoreProvider) {
    return new PreferencesRepository_Factory(dataStoreProvider);
  }

  public static PreferencesRepository newInstance(DataStore<Preferences> dataStore) {
    return new PreferencesRepository(dataStore);
  }
}
